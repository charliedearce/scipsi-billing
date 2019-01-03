Imports System.Data
Imports System.Data.SqlClient
Imports CrystalDecisions.CrystalReports.Engine
Imports CrystalDecisions.Shared
Imports System.IO
Module NewAdminMod
    Public sysdate As Date

    Public Sub alreadyuser()
        ' myCon.close()
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_users WHERE u_uname ='{0}'", frmUser.txtRegUsername.Text), myCon)
            myReader = myCmd.ExecuteReader
            If myReader.HasRows Then
                With frmUser
                    .btn1.Text = "Update"
                    .btn2.Text = "Cancel"

                    .requser.Text = "Username already existed."
                    .btndelete.Visible = True
                End With
                If myReader.Read = True Then
                    With frmUser
                        .txtRegPassword.Text = myReader("u_pass")
                        .txtRegEmpname.Text = myReader("u_fulname")
                        .cbRegRole.Text = myReader("u_role")
                    End With
                End If
            Else
                With frmUser
                    .btn1.Text = "Register"
                    .btn2.Text = "Clear"
                    .btndelete.Visible = False
                    .requser.Text = ""
                    .txtRegPassword.Text = ""
                    .txtRegEmpname.Text = ""
                    .cbRegRole.Text = ""
                End With
            End If
            myCon.close()
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub


    Public Sub uservalidation()
        With frmUser
            If .txtRegUsername.Text = "" And .txtRegRetypePass.Text = "" And .txtRegPassword.Text = "" And .txtRegEmpname.Text = "" Then
                .requser.Text = "Please enter username"
                .reqpass.Text = "Please enter password"
                .reqrepass.Text = "Please retype password"
                .reqempname.Text = "Please enter employee name"
            ElseIf .txtRegUsername.Text = "" Then
                .requser.Text = "Please enter username"
            ElseIf .txtRegPassword.Text = "" Then
                .reqpass.Text = "Please enter password"
            ElseIf .txtRegRetypePass.Text = "" Then
                .reqrepass.Text = "Please retype password"
            ElseIf .txtRegEmpname.Text = "" Then
                .reqempname.Text = "Please enter employee name"
            ElseIf .txtRegPassword.Text <> .txtRegRetypePass.Text Then
                .reqrepass.Text = "Password mismatch."
            Else
                With frmUser
                    If .btn1.Text = "Update" Then
                        userupdate()
                        clearuser()
                    ElseIf .btn1.Text = "Register" Then
                        registeruser()
                        clearuser()
                    End If
                End With

            End If
        End With
    End Sub

    Public Sub clearuser()
        With frmUser
            .txtRegUsername.Text = ""
            .txtRegRetypePass.Text = ""
            .txtRegPassword.Text = ""
            .txtRegEmpname.Text = ""
            .requser.Text = ""
            .reqrepass.Text = ""
            .reqpass.Text = ""
            .reqempname.Text = ""
            .btn1.Text = "Register"
            .btn2.Text = "Clear"
            .btndelete.Visible = False
        End With
    End Sub

    Public Sub userupdate()
        Try
            myCon.open()
            With frmUser
                myCmd = New SqlCommand("UPDATE tbl_users SET u_uname = '" & .txtRegUsername.Text & "',u_pass = '" & .txtRegPassword.Text & _
      "', u_fulname = '" & .txtRegEmpname.Text & "',u_role = '" & .cbRegRole.Text & "' WHERE u_uname = '" & .txtRegUsername.Text & "'", myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub deleteuser()
        Try
            Dim result As Integer = MessageBox.Show("Are you sure you want to delete this user: " + frmUser.txtRegUsername.Text + "?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                myCon.open()
                myCmd = New SqlCommand(String.Format("DELETE FROM tbl_users WHERE u_uname = '{0}'", frmUser.txtRegUsername.Text), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                clearuser()
            End If
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub registeruser()
        Try
            With frmUser
                myCon.open()
                myCmd = New SqlCommand(String.Format("INSERT INTO tbl_users (u_uname,u_pass,u_fulname,u_role)VALUES ('{0}','{1}','{2}','{3}')", .txtRegUsername.Text, .txtRegPassword.Text, .txtRegEmpname.Text, .cbRegRole.Text), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            End With
            MsgBox("User Successfully Registered.", vbOKOnly, Title:="Message")
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub generatebillnumber()
        ' systemtime(sysdate)
        Try
            With frmgenbill
                If .txtfrom.Text = "" Or .txtto.Text = "" Then
                    MsgBox("Please fill all fields.", vbCritical, "Generate Invoice")
                ElseIf Convert.ToInt64(.txtfrom.Text) > Convert.ToInt64(.txtto.Text) Then
                    MsgBox("Invalid Number Increment, Please make sure that BILL # From < To.", vbCritical, "Generate Invoice")
                Else
                    Dim billfrom As Integer = .txtfrom.Text
                    Dim billto As Integer = .txtto.Text
                    myCon.open()
                    myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_bill_no WHERE (((b_num > '{0}') and (b_num < '{1}')) OR ((b_num = '{0}') OR (b_num = '{1}')))", String.Format("{0:0000000000}", billfrom.ToString("0000000000")), String.Format("{0:0000000000}", billto.ToString("0000000000"))), myCon)
                    myReader = myCmd.ExecuteReader
                    If myReader.HasRows Then
                        myCon.close()
                        MsgBox("Some of generated invoice is already existing, please double check the bill numbers.", vbCritical, "Generate Invoice")
                    Else
                        myCon.close()
                        myCon.open()
                        For index As Integer = .txtfrom.Text To .txtto.Text
                            Dim myCmd1 = New SqlCommand(String.Format("INSERT INTO tbl_bill_no (b_num)VALUES ('{0}')", String.Format("{0:0000000000}", index.ToString("0000000000"))), myCon)
                            myCmd1.ExecuteNonQuery()
                        Next
                        myCon.close()
                        singinlogs("billnumber", "Added Bill# From: " + .txtfrom.Text + " TO " + .txtto.Text + " by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
                        .txtfrom.Text = ""
                        .txtto.Text = ""
                        MsgBox("Bill # Successfully Generated.", vbExclamation, "Message")
                    End If
                    myCon.close()
                    viewbillnumber()
                End If
            End With
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub deletebillnumber()
        ' systemtime(sysdate)
        Dim rows As Integer
        Try
            With frmgenbill
                If .txtfrom.Text = "" Or .txtto.Text = "" Then
                    MsgBox("Please fill all fields.", vbCritical, "Generate Invoice")
                ElseIf Convert.ToInt64(.txtfrom.Text) > Convert.ToInt64(.txtto.Text) Then
                    MsgBox("Invalid Number Increment, Please make sure that BILL # From < To.", vbCritical, "Generate Invoice")
                Else
                    myCon.open()
                    For index As Integer = .txtfrom.Text To .txtto.Text
                        Dim myCmd1 = New SqlCommand(String.Format("DELETE FROM tbl_bill_no WHERE b_num = ('{0}')", String.Format("{0:0000000000}", index.ToString("0000000000"))), myCon)
                        rows = myCmd1.ExecuteNonQuery()
                    Next
                    myCon.close()
                    viewbillnumber()
                    If rows = 0 Then
                        MsgBox("Bill # Not found.", vbCritical, "ERROR")
                    Else
                        MsgBox("Bill # Successfully Deleted.", vbExclamation, "Message")
                        singinlogs("billnumber", "Deleted Bill# From: " + .txtfrom.Text + " TO " + .txtto.Text + " by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
                        .txtfrom.Text = ""
                        .txtto.Text = ""
                    End If

                End If
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub updatebillnumber()
        Try
            myCon.open()
            Dim search As Integer = frmgenbill.txtsearch.Text
            myCmd = New SqlCommand(String.Format("UPDATE tbl_bill_no SET b_user = '' WHERE b_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
            frmgenbill.lbluser.Text = "USED BY:"
            myCmd.ExecuteNonQuery()
            myCon.close()
            MsgBox("Bill number User Cleared.", vbExclamation, "Message")
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub searchbillnumber()
        Try
            myCon.open()
            Dim search As Integer = frmgenbill.txtsearch.Text
            myCmd = New SqlCommand(String.Format("SELECT b_num,b_user FROM tbl_bill_no WHERE b_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
            myReader = myCmd.ExecuteReader
            If myReader.HasRows = True Then
                If myReader.Read Then
                    frmgenbill.lbluser.Text = "USED BY: " + myReader("b_user")
                End If
                frmgenbill.notice.Text = ""
                frmgenbill.btnclear.Visible = True
            Else
                frmgenbill.notice.Text = "Bill # not found."
                frmgenbill.btnclear.Visible = False
                frmgenbill.lbluser.Text = "USED BY:"
            End If
            myCon.close()
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub viewbillnumber()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT b_num FROM tbl_bill_no ORDER BY b_id DESC", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmgenbill.dgBil.DataSource = dt.DefaultView
            myCmd.Dispose()
            myCon.close()
        Catch ex As Exception
            ''MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    'OR NUMBER
    Public Sub generateORnumber()
        'systemtime(sysdate)
        Try
            With frmgenor
                If .txtfrom.Text = "" Or .txtto.Text = "" Then
                    MsgBox("Please fill all fields.", vbCritical, "Generate OR")
                ElseIf Convert.ToInt64(.txtfrom.Text) > Convert.ToInt64(.txtto.Text) Then
                    MsgBox("Invalid Number Increment, Please make sure that OR # From < To.", vbCritical, "Generate OR")
                Else
                    Dim orfrom As Integer = .txtfrom.Text
                    Dim orto As Integer = .txtto.Text
                    myCon.open()
                    myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_or_no WHERE (((or_num > '{0}') and (or_num < '{1}')) OR ((or_num = '{0}') OR (or_num = '{1}')))", String.Format("{0:0000000000}", orfrom.ToString("0000000000")), String.Format("{0:0000000000}", orto.ToString("0000000000"))), myCon)
                    myReader = myCmd.ExecuteReader
                    If myReader.HasRows Then
                        myCon.close()
                        MsgBox("Some of generated OR # is already existing, please double check the OR numbers.", vbCritical, "Generate OR")
                    Else
                        myCon.close()
                        myCon.open()
                        For index As Integer = .txtfrom.Text To .txtto.Text
                            Dim myCmd1 = New SqlCommand(String.Format("INSERT INTO tbl_or_no (or_num)VALUES ('{0}')", String.Format("{0:0000000000}", index.ToString("0000000000"))), myCon)
                            myCmd1.ExecuteNonQuery()
                        Next
                        myCon.close()
                        MsgBox("OR # Successfully Generated.", vbExclamation, "Message")
                        singinlogs("ornumber", "Added OR# From: " + .txtfrom.Text + " TO " + .txtto.Text + " by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
                        .txtfrom.Text = ""
                        .txtto.Text = ""
                    End If
                    myCon.close()
                    viewornumber()
                End If
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub deleteornumber()
        ' systemtime(sysdate)
        Dim rows As Integer
        Try
            With frmgenor
                If .txtfrom.Text = "" Or .txtto.Text = "" Then
                    MsgBox("Please fill all fields.", vbCritical, "Generate OR")
                ElseIf Convert.ToInt64(.txtfrom.Text) > Convert.ToInt64(.txtto.Text) Then
                    MsgBox("Invalid Number Increment, Please make sure that OR # From < To.", vbCritical, "Generate OR")
                Else
                    myCon.open()
                    For index As Integer = .txtfrom.Text To .txtto.Text
                        Dim myCmd1 = New SqlCommand(String.Format("DELETE FROM tbl_or_no WHERE or_num = ('{0}')", String.Format("{0:0000000000}", index.ToString("0000000000"))), myCon)
                        rows = myCmd1.ExecuteNonQuery()
                    Next
                    
                    myCon.close()
                    viewornumber()
                    If rows = 0 Then
                        MsgBox("OR# not found.", vbCritical, "ERROR")
                    Else
                        MsgBox("OR # Successfully Deleted.", vbExclamation, "Message")
                        singinlogs("ornumber", "Deleted OR# From: " + .txtfrom.Text + " TO " + .txtto.Text + " by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
                        .txtfrom.Text = ""
                        .txtto.Text = ""
                    End If
                End If
            End With
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub updateornumber()
        Try
            myCon.open()
            Dim search As Integer = frmgenor.txtsearch.Text
            myCmd = New SqlCommand(String.Format("UPDATE tbl_or_no SET or_user = '' WHERE or_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
            frmgenor.lbluser.Text = "USED BY:"
            myCmd.ExecuteNonQuery()
            myCon.close()
            MsgBox("Bill number User Cleared.", vbExclamation, "Message")
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub searchornumber()
        Try
            myCon.open()
            Dim search As Integer = frmgenor.txtsearch.Text
            myCmd = New SqlCommand(String.Format("SELECT or_num,or_user FROM tbl_or_no WHERE or_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
            myReader = myCmd.ExecuteReader
            If myReader.HasRows = True Then
                If myReader.Read Then
                    frmgenor.lbluser.Text = "USED BY: " + myReader("or_user")
                End If
                frmgenor.notice.Text = ""
                frmgenor.btnclear.Visible = True
            Else
                frmgenor.notice.Text = "OR # not found."
                frmgenor.btnclear.Visible = False
                frmgenor.lbluser.Text = "USED BY:"
            End If
            myCon.close()
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub viewornumber()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT or_num FROM tbl_or_no ORDER BY or_id DESC", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmgenor.dgor.DataSource = dt.DefaultView
            myCmd.Dispose()
            myCon.close()
        Catch ex As Exception
            ''MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub searchbilltrans()

        Try
            myCon.open()
            With frmbilltrans
                Dim search As Integer = .txtbillnum.Text
                myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_bill_trans WHERE bt_bill_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                myReader = myCmd.ExecuteReader
                If myReader.HasRows = True Then
                    If myReader.Read Then
                        .txtAccountno.Text = myReader("bt_acc_num")
                        .txtaccname.Text = myReader("bt_account")
                        .txtVessel.Text = myReader("bt_vessel")
                        .txtVoyage.Text = myReader("bt_voyage")
                        .cbRoute.Text = myReader("bt_route")
                        .cbType.Text = myReader("bt_type")
                        .txtParticulars.Text = myReader("bt_particulars")
                        .cbstatus.Text = myReader("bt_status")
                        .cbprint.Text = myReader("bt_indiprint")
                        .lbldatetrans.Text = myReader("bt_date")
                        .lblemp.Text = myReader("bt_employee")
                        .txtperiod.Text = myReader("bt_per")

                        .lbltotal.Text = myReader("bt_total")
                        .lblppa.Text = myReader("bt_ppa")
                        .lbldisc.Text = myReader("bt_disc")
                        .lblnet.Text = myReader("bt_net")
                        .lblvat.Text = myReader("bt_vat")
                        .lblscipsi.Text = myReader("bt_scipsi")

                    End If
                    .btn1.Visible = True
                    .btn2.Visible = True
                Else
                    clearbilltrans()
                End If
            End With
            myCon.close()
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub clearbilltrans()
        With frmbilltrans
            .txtAccountno.Text = ""
            .txtaccname.Text = ""
            .txtVessel.Text = ""
            .txtVoyage.Text = ""
            .cbRoute.Text = ""
            .cbType.Text = ""
            .txtParticulars.Text = ""
            .cbstatus.Text = ""
            .cbprint.Text = ""
            .lbldatetrans.Text = ""
            .lblemp.Text = ""
            .txtperiod.Text = ""
            .lbltotal.Text = 0
            .lblppa.Text = 0
            .lbldisc.Text = 0
            .lblnet.Text = 0
            .lblvat.Text = 0
            .lblscipsi.Text = 0
            .chkper.Checked = False
            .btn1.Visible = False
            .btn2.Visible = False

        End With
    End Sub

    Public Sub billvalidation()
        With frmbilltrans
            If .txtAccountno.Text = "" Or .txtaccname.Text = "" Or .txtVessel.Text = "" Or .txtVoyage.Text = "" Then
                MsgBox("Please fill all important fields.", vbCritical, "Bill Invoice Transaction")
            Else
                updatebilltrans()
                clearbilltrans()
            End If
        End With
    End Sub

    Public Sub updatebilltrans()
        ' systemtime(sysdate)
        With frmbilltrans
            Dim time As Date = .lbldatetrans.Text


            Dim month As String = time.ToString("MM")
            Dim day As String = time.ToString("dd")
            Dim year As String = time.ToString("yyyy")
            Dim period As String
            Dim reference As String
            Dim ref As String



            If month = "01" And day <= 25 Then
                period = year + "001"
                reference = "RB010"
            ElseIf month = "01" And day > 25 Then
                period = year + "002"
                reference = "RB020"
            ElseIf month = "02" And day <= 25 Then
                period = year + "002"
                reference = "RB020"
            ElseIf month = "02" And day > 25 Then
                period = year + "003"
                reference = "RB030"
            ElseIf month = "03" And day <= 25 Then
                period = year + "003"
                reference = "RB030"
            ElseIf month = "03" And day > 25 Then
                period = year + "004"
                reference = "RB040"
            ElseIf month = "04" And day <= 25 Then
                period = year + "004"
                reference = "RB040"
            ElseIf month = "04" And day > 25 Then
                period = year + "005"
                reference = "RB050"
            ElseIf month = "05" And day <= 25 Then
                period = year + "005"
                reference = "RB050"
            ElseIf month = "05" And day > 25 Then
                period = year + "006"
                reference = "RB060"
            ElseIf month = "06" And day <= 25 Then
                period = year + "006"
                reference = "RB060"
            ElseIf month = "06" And day > 25 Then
                period = year + "007"
                reference = "RB070"
            ElseIf month = "07" And day <= 25 Then
                period = year + "007"
                reference = "RB070"
            ElseIf month = "07" And day > 25 Then
                period = year + "008"
                reference = "RB080"
            ElseIf month = "08" And day <= 25 Then
                period = year + "008"
                reference = "RB080"
            ElseIf month = "08" And day > 25 Then
                period = year + "009"
                reference = "RB090"
            ElseIf month = "09" And day <= 25 Then
                period = year + "009"
                reference = "RB090"
            ElseIf month = "09" And day > 25 Then
                period = year + "010"
                reference = "RB100"
            ElseIf month = "10" And day <= 25 Then
                period = year + "010"
                reference = "RB100"
            ElseIf month = "10" And day > 25 Then
                period = year + "011"
                reference = "RB110"
            ElseIf month = "11" And day <= 25 Then
                period = year + "011"
                reference = "RB110"
            ElseIf month = "11" And day > 25 Then
                period = year + "012"
                reference = "RB120"
            ElseIf month = "12" And day <= 31 Then
                period = year + "012"
                reference = "RB120"
            End If
            Dim no As String = CDbl(.txtbillnum.Text)
            ref = reference + no

            If frmbilltrans.chkper.Checked = True Then
                period = frmbilltrans.txtperiod.Text
            End If

            Try

                myCon.open()

                Dim search As Integer = .txtbillnum.Text
                myCmd = New SqlCommand(String.Format("UPDATE tbl_bill_trans SET bt_account='{0}',bt_vessel='{1}',bt_voyage='{2}',bt_particulars='{3}',bt_route = '{4}',bt_type='{5}',bt_date='{6}',bt_acc_num='{7}',bt_per='{8}',bt_ref='{9}',bt_status='{10}',bt_indiprint='{11}' WHERE bt_bill_num='{12}'", .txtaccname.Text, .txtVessel.Text, .txtVoyage.Text, .txtParticulars.Text, .cbRoute.Text, .cbType.Text, time, .txtAccountno.Text, period, ref, .cbstatus.Text, .cbprint.Text, String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                singinlogs("billtrans", "Bill#: " + .txtbillnum.Text + " is MANUALLY UPDATED by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
                .txtbillnum.Text = ""
                .txtbillnum.Focus()
                MsgBox("Bill Transaction updated.", vbExclamation, "Message")
            Catch ex As Exception
                ' 'MsgBox(ex.Message)
            Finally
                If (myCon.State = ConnectionState.Open) Then
                    myCon.close()
                End If
            End Try
        End With
    End Sub

    Public Sub viewbilltrans()
        On Error Resume Next
        myCon.open()
        Dim search As Integer = frmbilltrans.txtbillnum.Text
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)
        myCmd = New SqlCommand(String.Format("SELECT it_qty,it_unit,it_service,it_cargo,it_rate,it_disc,it_gross FROM tbl_item_trans WHERE it_bill_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmbilltrans.dgbill.DataSource = dt.DefaultView
        myCon.close()
    End Sub

    Public Sub deletebilltrans()
        ' systemtime(sysdate)
        Try
            With frmbilltrans
                Dim result As Integer = MessageBox.Show("Are you sure you want to delete this Transaction: " + .txtbillnum.Text + "?", "Caution!", MessageBoxButtons.YesNo)
                If result = DialogResult.Yes Then
                    myCon.open()
                    Dim search As Integer = frmbilltrans.txtbillnum.Text
                    Dim mycmd1 = New SqlCommand(String.Format("INSERT INTO tbl_bill_no (b_num) VALUES ('{0}')", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                    mycmd1.ExecuteNonQuery()
                    myCmd = New SqlCommand(String.Format("DELETE FROM tbl_bill_trans WHERE bt_bill_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                    myCmd.ExecuteNonQuery()
                    Dim myCmd2 = New SqlCommand(String.Format("DELETE FROM tbl_item_trans WHERE it_bill_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                    myCmd2.ExecuteNonQuery()
                    myCon.close()
                    singinlogs("billtrans", "Bill#: " + .txtbillnum.Text + " is MANUALLY DELETED by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
                    .txtbillnum.Text = ""
                    clearbilltrans()
                    .txtbillnum.Focus()
                    viewbilltrans()
                End If
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub searchortrans()
        Try
            myCon.open()
            With frmortrans
                Dim search As Integer = .txtornum.Text
                myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_or_trans WHERE or_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                myReader = myCmd.ExecuteReader
                If myReader.HasRows = True Then
                    If myReader.Read Then
                        .txtAccountno.Text = myReader("or_acc_num")
                        .txtaccname.Text = myReader("or_accname")
                        .cbDeposit.Text = myReader("or_bank")
                        .cbstatus.Text = myReader("or_status")
                        .cbprint.Text = myReader("or_indiprint")
                        .lbldatetrans.Text = myReader("or_date")
                        .lblemp.Text = myReader("or_employee")
                        .txtperiod.Text = myReader("or_period")
                        .txtremarks.Text = myReader("or_remarks")
                        .txtcheck.Text = myReader("or_check_num")

                        .lblvat.Text = myReader("or_bill_vat")
                        .lblamount.Text = myReader("or_bill_amount")
                        .lblhtax.Text = myReader("or_htax")
                        .lbltotal.Text = myReader("or_net")
                    End If
                    .btn1.Visible = True
                    .btn2.Visible = True
                Else
                    clearortrans()
                End If
            End With
            myCon.close()
        Catch ex As Exception
            ' MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub orbank()
        myCon.close()
        Try
            myCon.open()
            myCmd = New SqlCommand("SELECT * FROM tbl_bank", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            Dim table As New DataTable
            myAdapter.Fill(table)
            With frmortrans
                .cbDeposit.DataSource = New BindingSource(table, Nothing)
                .cbDeposit.DisplayMember = "b_code"
                .cbDeposit.ValueMember = "b_code"
            End With
            myAdapter.Dispose()
            myCon.close()
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub client(ByVal accountname As String)
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_account WHERE ac_code = '{0}'", accountname), myCon)
            myReader = myCmd.ExecuteReader()

            If myReader.HasRows Then
                If myReader.Read Then
                    If frmortrans.txtAccountno.Focused = True Then
                        frmortrans.txtaccname.Text = myReader("ac_name")
                        frmortrans.btn1.Enabled = True
                    ElseIf frmbilltrans.txtAccountno.Focused = True Then
                        frmbilltrans.txtaccname.Text = myReader("ac_name")
                        frmbilltrans.btn1.Enabled = True
                    End If
                End If
            Else
                If frmortrans.txtAccountno.Focused = True Then
                    frmortrans.btn1.Enabled = False
                ElseIf frmbilltrans.txtAccountno.Focused = True Then
                    frmbilltrans.btn1.Enabled = False
                End If
            End If
            myReader.Close()
            myCon.close()
        Catch ex As Exception
            ''MsgBox(ex.Message)           
        End Try

    End Sub

    Public Sub clearortrans()
        With frmortrans
            .txtAccountno.Text = ""
            .txtaccname.Text = ""
            .txtcheck.Text = ""
            .txtremarks.Text = ""
            .cbstatus.Text = ""
            .cbprint.Text = ""
            .lbldatetrans.Text = ""
            .lblemp.Text = ""
            .txtperiod.Text = ""
            .lbltotal.Text = 0
            .lblamount.Text = 0
            .lblhtax.Text = 0
            .lblvat.Text = 0
            .chkper.Checked = False

            .btn1.Visible = False
            .btn2.Visible = False

        End With
    End Sub

    Public Sub viewortrans()
        On Error Resume Next
        myCon.open()
        Dim search As Integer = frmortrans.txtornum.Text
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)
        myCmd = New SqlCommand(String.Format("SELECT ot_bill_no,ot_bill_vat,ot_scipsi,ot_citw,ot_net,ot_disc FROM tbl_orbill_trans WHERE ot_or_no = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)
        frmortrans.dgor.DataSource = dt.DefaultView
        myCon.close()
    End Sub

    Public Sub deleteortrans()
        ' systemtime(sysdate)
        Try
            With frmortrans
                Dim result As Integer = MessageBox.Show("Are you sure you want to delete this Transaction: " + .txtornum.Text + "?", "Caution!", MessageBoxButtons.YesNo)
                If result = DialogResult.Yes Then
                    myCon.open()
                    Dim search As Integer = .txtornum.Text
                    Dim mycmd1 = New SqlCommand(String.Format("INSERT INTO tbl_or_no (or_num) VALUES ('{0}')", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                    mycmd1.ExecuteNonQuery()
                    myCmd = New SqlCommand(String.Format("DELETE FROM tbl_or_trans WHERE or_num = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                    myCmd.ExecuteNonQuery()
                    Dim myCmd2 = New SqlCommand(String.Format("DELETE FROM tbl_orbill_trans WHERE ot_or_no = '{0}'", String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                    myCmd2.ExecuteNonQuery()
                    myCon.close()
                    singinlogs("ortrans", "OR#: " + .txtornum.Text + " is MANUALLY DELETED by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
                    .txtornum.Text = ""
                    clearortrans()
                    .txtornum.Focus()
                    viewortrans()
                End If
            End With
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub updateortrans()
        '  systemtime(sysdate)
        With frmortrans
            Dim time As Date = .lbldatetrans.Text


            Dim month As String = time.ToString("MM")
            Dim day As String = time.ToString("dd")
            Dim year As String = time.ToString("yyyy")
            Dim period As String
            Dim reference As String
            Dim ref As String



            If month = "01" And day <= 25 Then
                period = year + "001"
                reference = "RB010"
            ElseIf month = "01" And day > 25 Then
                period = year + "002"
                reference = "RB020"
            ElseIf month = "02" And day <= 25 Then
                period = year + "002"
                reference = "RB020"
            ElseIf month = "02" And day > 25 Then
                period = year + "003"
                reference = "RB030"
            ElseIf month = "03" And day <= 25 Then
                period = year + "003"
                reference = "RB030"
            ElseIf month = "03" And day > 25 Then
                period = year + "004"
                reference = "RB040"
            ElseIf month = "04" And day <= 25 Then
                period = year + "004"
                reference = "RB040"
            ElseIf month = "04" And day > 25 Then
                period = year + "005"
                reference = "RB050"
            ElseIf month = "05" And day <= 25 Then
                period = year + "005"
                reference = "RB050"
            ElseIf month = "05" And day > 25 Then
                period = year + "006"
                reference = "RB060"
            ElseIf month = "06" And day <= 25 Then
                period = year + "006"
                reference = "RB060"
            ElseIf month = "06" And day > 25 Then
                period = year + "007"
                reference = "RB070"
            ElseIf month = "07" And day <= 25 Then
                period = year + "007"
                reference = "RB070"
            ElseIf month = "07" And day > 25 Then
                period = year + "008"
                reference = "RB080"
            ElseIf month = "08" And day <= 25 Then
                period = year + "008"
                reference = "RB080"
            ElseIf month = "08" And day > 25 Then
                period = year + "009"
                reference = "RB090"
            ElseIf month = "09" And day <= 25 Then
                period = year + "009"
                reference = "RB090"
            ElseIf month = "09" And day > 25 Then
                period = year + "010"
                reference = "RB100"
            ElseIf month = "10" And day <= 25 Then
                period = year + "010"
                reference = "RB100"
            ElseIf month = "10" And day > 25 Then
                period = year + "011"
                reference = "RB110"
            ElseIf month = "11" And day <= 25 Then
                period = year + "011"
                reference = "RB110"
            ElseIf month = "11" And day > 25 Then
                period = year + "012"
                reference = "RB120"
            ElseIf month = "12" And day <= 31 Then
                period = year + "012"
                reference = "RB120"
            End If
            Dim no As String = CDbl(.txtornum.Text)
            ref = reference + no

            If frmortrans.chkper.Checked = True Then
                period = frmortrans.txtperiod.Text
            End If

            Try

                myCon.open()

                Dim search As Integer = .txtornum.Text
                myCmd = New SqlCommand(String.Format("UPDATE tbl_or_trans SET or_acc_num='{0}',or_accname='{1}',or_bank='{2}',or_status='{3}',or_indiprint = '{4}',or_date='{5}',or_period='{6}',or_remarks='{7}',or_check_num='{8}' WHERE or_num='{9}'", .txtAccountno.Text, .txtaccname.Text, .cbDeposit.Text, .cbstatus.Text, .cbprint.Text, .lbldatetrans.Text, period, .txtremarks.Text, .txtcheck.Text, String.Format("{0:0000000000}", search.ToString("0000000000"))), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                singinlogs("ortrans", "OR#: " + .txtornum.Text + " is MANUALLY UPDATED by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
                .txtornum.Text = ""
                .txtornum.Focus()
                MsgBox("OR Transaction updated.", vbExclamation, "Message")
            Catch ex As Exception
                MsgBox(ex.Message)
            Finally
                If (myCon.State = ConnectionState.Open) Then
                    myCon.close()
                End If
            End Try
        End With
    End Sub

    Public Sub orvalidation()
        With frmortrans
            If .txtAccountno.Text = "" Or .txtaccname.Text = "" Then
                MsgBox("Please fill all important fields.", vbCritical, "OR Transaction")
            Else
                updateortrans()
                clearortrans()
            End If
        End With
    End Sub

    Public Sub searchclient(ByVal account As String)
        Try
            With frmclient
                myCon.open()
                myCmd = New SqlCommand(String.Format("SELECT ac_code,ac_name FROM tbl_account WHERE ac_code = '{0}'", account), myCon)
                myReader = myCmd.ExecuteReader()
                If myReader.HasRows Then
                    If myReader.Read Then
                        .btn1.Text = "Update"
                        .btn2.Visible = True
                        .txtaccname.Text = myReader("ac_name")
                    End If
                Else
                    .btn1.Text = "Register"
                    .btn2.Visible = False
                    .txtaccname.Text = ""
                End If
                myCon.close()
            End With
        Catch ex As Exception
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub registerclient()
        Try
            With frmclient
                If .txtAccountno.Text = "     -" Or .txtaccname.Text = "" Then
                    MsgBox("Please fill all important fields.", vbCritical, "Update Client Info's")
                Else

                    myCon.open()
                    myCmd = New SqlCommand(String.Format("INSERT INTO tbl_account (ac_code,ac_name)VALUES ('{0}','{1}')", .txtAccountno.Text, .txtaccname.Text), myCon)
                    myCmd.ExecuteNonQuery()
                    myCon.close()

                    MsgBox("Account Successfully Registered.", vbInformation, "Register Client Info's")
                    .txtAccountno.Text = ""
                    .txtaccname.Text = ""
                End If
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub updateclient()
        Try
            With frmclient
                myCon.open()
                myCmd = New SqlCommand(String.Format("UPDATE tbl_account SET ac_name = '{0}' WHERE ac_code = '{1}'", .txtaccname.Text, .txtAccountno.Text), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                MsgBox("Account Successfully Updated.", vbInformation, "Update Client Info's")
                .txtAccountno.Text = ""
                .txtaccname.Text = ""
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub deleteclient()
        Try
            With frmclient
                myCon.open()
                myCmd = New SqlCommand(String.Format("DELETE FROM tbl_account WHERE ac_code = '{0}'", .txtAccountno.Text), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                MsgBox("Account Successfully Deleted.", vbInformation, "Delete Client Info's")
                .txtAccountno.Text = ""
                .txtaccname.Text = ""
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub registerservice()
        Try
            With frmservice
                If .txtServiceDesc.Text = "" Or .txtServiceCode.Text = "" Then
                    MsgBox("Please fill all important fields.", vbCritical, "Register Services Info's")
                Else
                    myCon.open()
                    myCmd = New SqlCommand(String.Format("INSERT INTO tbl_service (s_code,s_desc,s_type)VALUES ('{0}','{1}','{2}')", .txtServiceCode.Text, .txtServiceDesc.Text, .cbServiceType.Text), myCon)
                    myCmd.ExecuteNonQuery()
                    myReader.Close()
                    Dim myCmd1 = New SqlCommand(String.Format("Select * From tbl_scode WHERE sc_code = '{0}'", .txtServiceCode.Text), myCon)
                    Dim myReader1 = myCmd1.ExecuteReader
                    If myReader1.HasRows Then
                    Else
                        myReader1.close()
                        Dim myCmd2 = New SqlCommand(String.Format("INSERT INTO tbl_scode (sc_code)VALUES ('{0}')", .txtServiceCode.Text), myCon)
                        myCmd2.ExecuteNonQuery()
                    End If
                    myCon.close()
                    .txtServiceDesc.Text = ""
                    .txtServiceCode.Text = ""
                    MsgBox("Service Successfully Registered.", vbInformation, "Register Service Info's")
                End If
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub updateservice()
        Try
            With frmservice
                myCon.open()
                myCmd = New SqlCommand(String.Format("UPDATE tbl_service SET s_desc = '{0}',s_type = '{1}' WHERE s_code = '{2}'", .txtServiceDesc.Text, .cbServiceType.Text, .txtServiceCode.Text), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                MsgBox("Service Successfully Updated.", vbInformation, "Update Service Info's")
                .txtServiceCode.Text = ""
                .txtServiceDesc.Text = ""
                .cbServiceType.Text = ""
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub searchservice()
        Try
            With frmservice
                myCon.open()
                myCmd = New SqlCommand(String.Format("SELECT s_desc,s_type FROM tbl_service WHERE s_code = '{0}'", .txtServiceCode.Text), myCon)
                myReader = myCmd.ExecuteReader()
                If myReader.HasRows Then
                    If myReader.Read Then
                        .txtServiceDesc.Text = myReader("s_desc")
                        .cbServiceType.Text = myReader("s_type")
                    End If
                    .btn1.Text = "Update"
                    .btn2.Visible = True
                Else
                    .btn1.Text = "Register"
                    .btn2.Visible = False
                End If
                myCon.close()
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub deleteservice()
        Try
            With frmservice
                myCon.open()
                myCmd = New SqlCommand("DELETE FROM tbl_service WHERE s_code = '" & .txtServiceCode.Text & "'", myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
                MsgBox("Service Successfully Deleted.", vbInformation, "Delete Service Info's")
                .txtServiceCode.Text = ""
                .txtServiceDesc.Text = ""
                .cbServiceType.Text = ""
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub viewservicecode()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)

            myCmd = New SqlCommand("SELECT * FROM tbl_scode", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)

            frmtariff.cbTarrifServiceCode.DataSource = dt
            frmtariff.cbTarrifServiceCode.DisplayMember = "sc_code"
            myCon.close()
        Catch ex As Exception
            ''MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub viewaliasname(ByVal scode As String)
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)

            myCmd = New SqlCommand(String.Format("SELECT s_code,s_desc FROM tbl_service WHERE s_code = '{0}'", scode), myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)

            frmtariff.cbAlias.DataSource = dt
            frmtariff.cbAlias.DisplayMember = "s_desc"
            myCon.close()
        Catch ex As Exception
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub searchtariff()
        With frmtariff
            Try
                myCon.open()
                Dim dt As New DataTable
                Dim ds As New DataSet
                ds.Tables.Add(dt)
                myCmd = New SqlCommand(String.Format("SELECT t_desc,t_basis,t_unit,t_class,t_dom_arrastre,t_dom_stevedor,t_dom_other,t_for_arrastre,t_for_stevedor,t_for_other,t_scode,t_sname FROM tbl_tarrif WHERE t_code = '{0}'", .txtTarrifCode.Text), myCon)
                myReader = myCmd.ExecuteReader()
                If myReader.HasRows Then
                    If myReader.Read Then
                        .txtTarrifDesc.Text = myReader("t_desc")
                        .txtTarrifBasis.Text = myReader("t_basis")
                        .txtTarrifUnit.Text = myReader("t_unit")
                        .cbTarrifClass.Text = myReader("t_class")
                        .darrastre.Text = myReader("t_dom_arrastre")
                        .dstevedor.Text = myReader("t_dom_stevedor")
                        .dother.Text = myReader("t_dom_other")
                        .farrastre.Text = myReader("t_for_arrastre")
                        .fstevedor.Text = myReader("t_for_stevedor")
                        .fother.Text = myReader("t_for_other")
                        .cbTarrifServiceCode.Text = myReader("t_scode")
                        '.cbAlias.Text = myReader("t_sname")
                    End If
                    .btn1.Text = "Update"
                    .btn2.Visible = True
                Else
                    .btn1.Text = "Register"
                    .btn2.Visible = False
                    cleartariff()
                End If
                myCon.close()
            Catch ex As Exception
            Finally
                If (myCon.State = ConnectionState.Open) Then
                    myCon.close()
                End If
            End Try
        End With
    End Sub

    Public Sub searchalias()
        Try
            With frmtariff
                myCon.open()
                Dim dt As New DataTable
                Dim ds As New DataSet
                ds.Tables.Add(dt)
                myCmd = New SqlCommand(String.Format("SELECT t_sname FROM tbl_tarrif WHERE t_code = '{0}'", .txtTarrifCode.Text), myCon)
                myReader = myCmd.ExecuteReader()
                If myReader.HasRows Then
                    If myReader.Read Then
                        .cbAlias.Text = myReader("t_sname")
                    End If
                End If
                myCon.close()
            End With
        Catch ex As Exception
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub updatetariff()
        Try
            myCon.open()
            With frmtariff
                myCmd = New SqlCommand(String.Format("UPDATE tbl_tarrif SET t_desc = '{0}',t_basis = '{1}',t_unit = '{2}',t_class = '{3}',t_dom_arrastre = '{4}',t_dom_stevedor = '{5}',t_dom_other = '{6}',t_for_arrastre = '{7}',t_for_stevedor = '{8}',t_for_other = '{9}',t_scode = '{10}' ,t_sname = '{11}' WHERE t_code ='{12}'", .txtTarrifDesc.Text, .txtTarrifBasis.Text, .txtTarrifUnit.Text, .cbTarrifClass.Text, .darrastre.Text, .dstevedor.Text, .dother.Text, .farrastre.Text, .fstevedor.Text, .fother.Text, .cbTarrifServiceCode.Text, .cbAlias.Text, .txtTarrifCode.Text), myCon)
                myCmd.ExecuteNonQuery()
            End With
            myCon.close()
            MsgBox("Tariff Successfully Updated.", vbInformation, "Update Tariff Info's")
            cleartariff()
            frmtariff.txtTarrifCode.Text = ""
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try


    End Sub

    Public Sub cleartariff()
        With frmtariff
            .txtTarrifDesc.Text = ""
            .txtTarrifBasis.Text = ""
            .txtTarrifUnit.Text = ""
            .cbTarrifClass.Text = ""
            .darrastre.Text = ""
            .dstevedor.Text = ""
            .dother.Text = ""
            .farrastre.Text = ""
            .fstevedor.Text = ""
            .fother.Text = ""
            .cbTarrifServiceCode.Text = ""
            .cbAlias.Text = ""
        End With
    End Sub

    Public Sub registertariff()
        Try
            With frmtariff
                If .txtTarrifCode.Text = "" Or .txtTarrifDesc.Text = "" Or .txtTarrifBasis.Text = "" Or .txtTarrifUnit.Text = "" _
                    Or .darrastre.Text = "" Or .dstevedor.Text = "" Or .dother.Text = "" Or .farrastre.Text = "" _
                    Or .fstevedor.Text = "" Or .fother.Text = "" Or .cbAlias.Text = "" Then
                    MsgBox("Please fill all important fields.", vbCritical, "Register Tariff Info's")
                Else
                    myCon.open()
                    myCmd = New SqlCommand(String.Format("INSERT INTO tbl_tarrif (t_code,t_desc,t_basis,t_unit,t_class,t_dom_arrastre,t_dom_stevedor,t_dom_other,t_for_arrastre,t_for_stevedor,t_for_other,t_scode,t_sname) VALUES ('{0}','{1}','{2}','{3}','{4}','{5}','{6}','{7}','{8}','{9}','{10}','{11}','{12}')", .txtTarrifCode.Text, .txtTarrifDesc.Text, .txtTarrifBasis.Text, .txtTarrifUnit.Text, .cbTarrifClass.Text, .darrastre.Text, .dstevedor.Text, .dother.Text, .farrastre.Text, .fstevedor.Text, .fother.Text, .cbTarrifServiceCode.Text, .cbAlias.Text), myCon)
                    myCmd.ExecuteNonQuery()
                    myCmd.Dispose()
                    myCon.close()
                    MsgBox("Tariff Successfully Registered.", vbInformation, "Register Tariff Info's")
                    cleartariff()
                    .txtTarrifCode.Text = ""
                    .btn1.Text = "Register"
                End If
            End With
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub deletetariff()
        With frmtariff
            myCon.open()
            myCmd = New SqlCommand(String.Format("DELETE FROM tbl_tarrif WHERE t_code = '{0}'", frmtariff.txtTarrifCode.Text), myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
            MsgBox("Tariff Successfully Deleted.", vbInformation, "Delete Tariff Info's")
            cleartariff()
            .txtTarrifCode.Text = ""
            .btn1.Text = "Register"
            .btn2.Visible = False
        End With
    End Sub

    Public Sub exportsunbill()
        Try

            myCon.open()
            Dim ds As New DSBill
            myCmd = New SqlCommand("SunBill", myCon)
            If repBill.XtraTabControl1.SelectedTabPageIndex = 0 Then
                myCmd.Parameters.Add(New SqlParameter("@period", SqlDbType.Int)).Value = repBill.cbPeriod.Text
            ElseIf repBill.XtraTabControl1.SelectedTabPageIndex = 1 Then
                myCmd.Parameters.Add(New SqlParameter("@period", SqlDbType.Int)).Value = repBill.txtcusper.Text
            ElseIf repBill.XtraTabControl1.SelectedTabPageIndex = 2 Then
                myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = repBill.dtpBillFrom.Text
                myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = repBill.dtpBillTo.Text
            End If

            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "SunBill")
            Dim crystal As New SunBill
            crystal.SetDataSource(ds)

            repBill.SaveFileDialog1.Filter = "Excel files (*.xls)|*.xls"
            repBill.SaveFileDialog1.Title = "Save Bill Report"
            repBill.SaveFileDialog1.FileName = "Bill Report " + Date.Now.ToString("MM-dd-yyyy")
            repBill.SaveFileDialog1.ShowDialog()
            Dim strExportFile As String = repBill.SaveFileDialog1.FileName

            Dim CrExportOptions As ExportOptions
            Dim CrDiskFileDestinationOptions As New  _
            DiskFileDestinationOptions()
            Dim CrFormatTypeOptions As New ExcelFormatOptions
            CrDiskFileDestinationOptions.DiskFileName = strExportFile

            CrExportOptions = crystal.ExportOptions
            With CrExportOptions
                .ExportDestinationType = ExportDestinationType.DiskFile
                .ExportFormatType = ExportFormatType.ExcelWorkbook
                .DestinationOptions = CrDiskFileDestinationOptions
                .FormatOptions = CrFormatTypeOptions
            End With
            crystal.Export()
            myCon.close()
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub exportsunor()
        Try

            myCon.open()

            Dim ds As New DSBill

            myCmd = New SqlCommand("SunOr", myCon)

            If repOr.XtraTabControl1.SelectedTabPageIndex = 0 Then
                myCmd.Parameters.Add(New SqlParameter("@period", SqlDbType.Int)).Value = repOr.cbPeriod.Text
            ElseIf repOr.XtraTabControl1.SelectedTabPageIndex = 1 Then
                myCmd.Parameters.Add(New SqlParameter("@period", SqlDbType.Int)).Value = repOr.txtcusper.Text
            ElseIf repOr.XtraTabControl1.SelectedTabPageIndex = 2 Then
                myCmd.Parameters.Add(New SqlParameter("@date_from", SqlDbType.DateTime)).Value = repOr.dtpBillFrom.Text
                myCmd.Parameters.Add(New SqlParameter("@date_to", SqlDbType.DateTime)).Value = repOr.dtpBillTo.Text
            End If
            myCmd.CommandType = CommandType.StoredProcedure
            myAdapter.SelectCommand = myCmd
            myAdapter.Fill(ds, "SunOr")

            Dim crystal As New SunOr
            crystal.SetDataSource(ds)

            repOr.SaveFileDialog1.Filter = "Excel files (*.xls)|*.xls"
            repOr.SaveFileDialog1.Title = "Save OR Report"
            repOr.SaveFileDialog1.FileName = "OR Report " + Date.Now.ToString("MM-dd-yyyy")
            repOr.SaveFileDialog1.ShowDialog()
            Dim strExportFile As String = repOr.SaveFileDialog1.FileName

            Dim CrExportOptions As ExportOptions
            Dim CrDiskFileDestinationOptions As New  _
            DiskFileDestinationOptions()
            Dim CrFormatTypeOptions As New ExcelFormatOptions
            CrDiskFileDestinationOptions.DiskFileName = strExportFile

            CrExportOptions = crystal.ExportOptions
            With CrExportOptions
                .ExportDestinationType = ExportDestinationType.DiskFile
                .ExportFormatType = ExportFormatType.ExcelWorkbook
                .DestinationOptions = CrDiskFileDestinationOptions
                .FormatOptions = CrFormatTypeOptions
            End With
            crystal.Export()
            myCon.close()
        Catch ex As Exception
            'MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub viewbillrequest()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT * FROM tbl_reqbill ORDER BY rb_id DESC ", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmbilltrans.DataGridView1.DataSource = dt.DefaultView
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub processbillrequest(ByVal id As Integer, ByVal billnum As Integer, ByVal type As String)
        '  systemtime(sysdate)
        Try
            If type = "Cancel" Then
                myCon.open()
                myCmd = New SqlCommand("UPDATE tbl_bill_trans SET bt_status = 'TRUE' WHERE bt_bill_num = '" & String.Format("{0:0000000000}", billnum.ToString("0000000000")) & "'", myCon)
                myCmd.ExecuteNonQuery()
                frmbilltrans.txtbillnum.Text = ""
                frmbilltrans.txtbillnum.Text = billnum
                myCon.close()
                MsgBox(billnum.ToString + " Successfully Processed")
                myCon.open()
                myCmd = New SqlCommand("UPDATE tbl_reqbill SET rb_status = 'Processed' WHERE rb_id = '" & id & "'", myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()

                singinlogs("billtrans", "Request to CANCEL BILL#: " + billnum.ToString + ", PROCESSED by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
            ElseIf type = "Edit" Then
                myCon.open()
                myCmd = New SqlCommand("UPDATE tbl_bill_trans SET bt_indiprint = 'N' WHERE bt_bill_num = '" & String.Format("{0:0000000000}", billnum.ToString("0000000000")) & "'", myCon)
                myCmd.ExecuteNonQuery()
                frmbilltrans.txtbillnum.Text = ""
                frmbilltrans.txtbillnum.Text = billnum
                myCon.close()
                MsgBox(billnum.ToString + " Successfully Processed")
                myCon.open()
                myCmd = New SqlCommand("UPDATE tbl_reqbill SET rb_status = 'Processed' WHERE rb_id = '" & id & "'", myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()

                singinlogs("billtrans", "Request to EDIT BILL#: " + billnum.ToString + ", PROCESSED by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
            ElseIf type = "Delete" Then
                deletebilltrans()

                myCon.open()
                myCmd = New SqlCommand("UPDATE tbl_reqbill SET rb_status = 'Processed' WHERE rb_id = '" & id & "'", myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            End If
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub vieworrequest()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT * FROM tbl_reqor ORDER BY ro_id DESC ", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmortrans.DataGridView1.DataSource = dt.DefaultView
            myCon.close()
        Catch ex As Exception
            ' MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub processorrequest(ByVal id As Integer, ByVal ornum As Integer, ByVal type As String)
        ' systemtime(sysdate)
        If type = "Cancel" Then
            myCon.open()
            myCmd = New SqlCommand("UPDATE tbl_or_trans SET or_status = 'TRUE' WHERE or_num = '" & String.Format("{0:0000000000}", ornum.ToString("0000000000")) & "'", myCon)
            myCmd.ExecuteNonQuery()
            frmortrans.txtornum.Text = ""
            frmortrans.txtornum.Text = ornum
            myCon.close()
            MsgBox(ornum.ToString + " Successfully Processed")
            myCon.open()
            myCmd = New SqlCommand("UPDATE tbl_reqor SET ro_status = 'Processed' WHERE ro_id = '" & id & "'", myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()

            singinlogs("ortrans", "Request to CANCEL OR#: " + ornum.ToString + ", PROCESSED by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
        ElseIf type = "Edit" Then
            myCon.open()
            myCmd = New SqlCommand("UPDATE tbl_or_trans SET or_indiprint = 'N' WHERE or_num = '" & String.Format("{0:0000000000}", ornum.ToString("0000000000")) & "'", myCon)
            myCmd.ExecuteNonQuery()
            frmortrans.txtornum.Text = ""
            frmortrans.txtornum.Text = ornum
            myCon.close()
            MsgBox(ornum.ToString + " Successfully Processed")
            myCon.open()
            myCmd = New SqlCommand("UPDATE tbl_reqor SET ro_status = 'Processed' WHERE ro_id = '" & id & "'", myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()

            singinlogs("ortrans", "Request to EDIT OR#: " + ornum.ToString + ", PROCESSED by " + frmControlpanel.BarEditItem2.EditValue + " | Time: " + sysdate)
        ElseIf type = "Delete" Then
            deleteortrans()

            myCon.open()
            myCmd = New SqlCommand("UPDATE tbl_reqor SET ro_status = 'Processed' WHERE ro_id = '" & id & "'", myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
        End If
    End Sub

    Public Sub viewsettings()
        Try
            myCon.Open()
            myCmd = New SqlCommand("Select * From tbl_settings", myCon)
            myReader = myCmd.ExecuteReader
            If myReader.HasRows Then
                If myReader.Read = True Then
                    With frmPercent
                        .vat.Text = myReader("vat")
                        .fppa.Text = myReader("fppa")
                        .dppa.Text = myReader("dppa")
                        .whtax.Text = myReader("ctiw")
                        .disc.Text = myReader("discount")
                        .cbPrint.Text = myReader("myprint")
                    End With
                End If
            End If
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub savesettings()
        Try
            With frmPercent
                myCon.open()
                myCmd = New SqlCommand(String.Format("UPDATE tbl_settings SET myprint = '{0}',vat = '{1}',fppa = '{2}',dppa = '{3}',ctiw = '{4}',discount = '{5}'", .cbPrint.Text, .vat.Text, .fppa.Text, .dppa.Text, .whtax.Text, .disc.Text), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            End With
            MsgBox("Settings Updated")
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub systemtime(ByRef mydate As Date)
        Try
            myCon.Open()
            myCmd = New SqlCommand("Select sysdate From tbl_settings", myCon)
            myReader = myCmd.ExecuteReader
            If myReader.HasRows Then
                If myReader.Read = True Then

                    mydate = myReader("sysdate")

                End If
            End If
            myCon.close()
        Catch ex As Exception
            frmControlpanel.Timer1.Enabled = False
        Finally
            frmControlpanel.Timer1.Enabled = True
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub viewbackdate()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT * FROM tbl_datesreq ORDER BY d_id DESC ", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmReqDate.DataGridView1.DataSource = dt.DefaultView
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub approveddate(ByVal id As Integer)
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_datesreq SET d_status = 'Approved' WHERE d_id = '" & id & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub denieddate(ByVal id As Integer)
        myCon.open()
        myCmd = New SqlCommand("UPDATE tbl_datesreq SET d_status = 'Denied' WHERE d_id = '" & id & "'", myCon)
        myCmd.ExecuteNonQuery()
        myCon.close()
    End Sub

    Public Sub autochangedate(ByRef yourdate As Date)
        Try
            myCon.open()
            With frmBilling
                myCmd = New SqlCommand("Select * From tbl_datesreq WHERE d_reqby='" & .txtnametemp.Text & "' AND d_status = 'Approved' AND d_use = 'N'", myCon)
                myReader = myCmd.ExecuteReader
                If myReader.HasRows Then
                    If myReader.Read = True Then
                        yourdate = myReader("d_date")
                    End If
                    .forsysdate.Enabled = False
                    frmor.forsysdate.Enabled = False
                    .BackDatesToolStripMenuItem.Text = "Restore Date"
                Else
                    .forsysdate.Enabled = True
                    frmor.forsysdate.Enabled = True
                    .BackDatesToolStripMenuItem.Text = "Back Dates"
                End If
            End With
            myCon.close()
        Catch ex As Exception
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
       
    End Sub

    Public Sub restoredate()
        Dim result As Integer = MessageBox.Show("Are you sure you want to restore System Date?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            myCon.open()
            myCmd = New SqlCommand("UPDATE tbl_datesreq SET d_use = 'Y' WHERE d_reqby='" & frmBilling.txtnametemp.Text & "' AND d_status = 'Approved'", myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
        End If
    End Sub

    Public Sub viewuserbackdates()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT * FROM tbl_datesreq WHERE d_reqby='" & frmBilling.txtnametemp.Text & "' ORDER BY d_id DESC", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmBackdates.DataGridView1.DataSource = dt.DefaultView
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub requestuserbackdate()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand(String.Format("INSERT INTO tbl_datesreq (d_date,d_reqby,d_status,d_use,d_reason) VALUES ('{0}', '{1}','Pending','N','{2}')", frmBackdates.txtdate.Text, frmBilling.txtnametemp.Text, frmBackdates.txtReason.Text), myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
            MsgBox("Request Successfully Sent!", vbExclamation, "Information")
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub deleterequestbackdate(ByVal myid As Integer)
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("DELETE FROM tbl_datesreq WHERE d_id = '{0}'", myid), myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
            MsgBox("Request Successfully Deleted!", vbExclamation, "Information")
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub viewuserbillrequest()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_reqbill WHERE rb_reqby='{0}' ORDER BY rb_id DESC", frmBilling.txtnametemp.Text), myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmbillrequest.DataGridView1.DataSource = dt.DefaultView
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub userbillrequest()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand(String.Format("INSERT INTO tbl_reqbill (rb_num,rb_type,rb_reason,rb_reqby,rb_status) VALUES ('{0}','{1}','{2}','{3}','Pending')", frmbillrequest.lblbillnumber.Text, frmbillrequest.cbtype.Text, frmbillrequest.txtReason.Text, frmBilling.txtnametemp.Text), myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
            MsgBox("Request Successfully Sent!", vbExclamation, "Information")
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub viewuserorrequest()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_reqor WHERE ro_reqby='{0}' ORDER BY ro_id DESC", frmBilling.txtnametemp.Text), myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmorrequest.DataGridView1.DataSource = dt.DefaultView
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub userorrequest()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand(String.Format("INSERT INTO tbl_reqor (ro_num,ro_type,ro_reason,ro_reqby,ro_status) VALUES ('{0}','{1}','{2}','{3}','Pending')", frmorrequest.lblornumber.Text, frmorrequest.cbtype.Text, frmorrequest.txtReason.Text, frmBilling.txtnametemp.Text), myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
            MsgBox("Request Successfully Sent!", vbExclamation, "Information")
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub deleterequestbill(ByVal myid As Integer)
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("DELETE FROM tbl_reqbill WHERE rb_id = '{0}'", myid), myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
            MsgBox("Request Successfully Deleted!", vbExclamation, "Information")
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub deleterequestor(ByVal myid As Integer)
        Try
            myCon.open()
            myCmd = New SqlCommand(String.Format("DELETE FROM tbl_reqor WHERE ro_id = '{0}'", myid), myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()
            MsgBox("Request Successfully Deleted!", vbExclamation, "Information")
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub singinlogs(ByVal type As String, ByVal message As String)
        Try
            If type = "Login" Then
                myCon.open()
                myCmd = New SqlCommand(String.Format("INSERT INTO tbl_logs (audit) VALUES ('{0}')", message), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            ElseIf type = "billnumber" Then
                myCon.open()
                myCmd = New SqlCommand(String.Format("INSERT INTO tbl_logs (b_desc) VALUES ('{0}')", message), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            ElseIf type = "ornumber" Then
                myCon.open()
                myCmd = New SqlCommand(String.Format("INSERT INTO tbl_logs (or_desc) VALUES ('{0}')", message), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            ElseIf type = "billtrans" Then
                myCon.open()
                myCmd = New SqlCommand(String.Format("INSERT INTO tbl_logs (btran_desc) VALUES ('{0}')", message), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            ElseIf type = "ortrans" Then
                myCon.open()
                myCmd = New SqlCommand(String.Format("INSERT INTO tbl_logs (ortran_desc) VALUES ('{0}')", message), myCon)
                myCmd.ExecuteNonQuery()
                myCon.close()
            End If

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try

    End Sub

    Public Sub changepassword()
        Try
            myCon.open()

            myCmd = New SqlCommand(String.Format("UPDATE tbl_users SET u_pass = '{0}' WHERE u_fulname = '{1}'", frmchangepass.txtpassword.Text, frmBilling.txtnametemp.Text), myCon)
            myCmd.ExecuteNonQuery()
            myCon.close()

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub cleanduplicates()
        Try
            Dim billrows, orrows, rows As String
            myCon.open()

            myCmd = New SqlCommand("DELETE FROM tbl_bill_trans WHERE bt_bill_num IN (SELECT bt_bill_num FROM tbl_bill_trans GROUP BY bt_bill_num HAVING COUNT(*) > 1) AND NOT bt_id IN (SELECT max(bt_id) FROM tbl_bill_trans GROUP BY bt_bill_num)", myCon)
            billrows = myCmd.ExecuteNonQuery()
            myCon.close()

            myCon.open()
            myCmd = New SqlCommand("DELETE FROM tbl_or_trans WHERE or_num IN (SELECT or_num FROM tbl_or_trans GROUP BY or_num HAVING COUNT(*) > 1) AND NOT or_id IN (SELECT max(or_id) FROM tbl_or_trans GROUP BY or_num)", myCon)
            orrows = myCmd.ExecuteNonQuery()
            myCon.close()

            rows = billrows + orrows
            If rows <> 0 Then
                MsgBox("System Deleted " + rows + " Duplicated datas...", vbInformation, "System Auto Clean")
            End If

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub viewlogin()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT audit FROM tbl_logs WHERE audit <> '' ORDER BY id DESC ", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmloginaudit.DataGridView1.DataSource = dt.DefaultView
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub viewtransaction()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT btran_desc FROM tbl_logs WHERE btran_desc <> '' ORDER BY id DESC ", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmtransaudit.DataGridView1.DataSource = dt.DefaultView
            myCon.close()

            myCon.open()
            Dim dt1 As New DataTable
            Dim ds1 As New DataSet
            ds1.Tables.Add(dt1)
            myCmd = New SqlCommand("SELECT ortran_desc FROM tbl_logs WHERE ortran_desc <> '' ORDER BY id DESC ", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt1)
            frmtransaudit.DataGridView2.DataSource = dt1.DefaultView
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub

    Public Sub viewnumberaudit()
        Try
            myCon.open()
            Dim dt As New DataTable
            Dim ds As New DataSet
            ds.Tables.Add(dt)
            myCmd = New SqlCommand("SELECT b_desc FROM tbl_logs WHERE b_desc <> '' ORDER BY id DESC ", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            frmnumberaudit.DataGridView1.DataSource = dt.DefaultView
            myCon.close()

            myCon.open()
            Dim dt1 As New DataTable
            Dim ds1 As New DataSet
            ds1.Tables.Add(dt1)
            myCmd = New SqlCommand("SELECT or_desc FROM tbl_logs WHERE or_desc <> '' ORDER BY id DESC ", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt1)
            frmnumberaudit.DataGridView2.DataSource = dt1.DefaultView
            myCon.close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            If (myCon.State = ConnectionState.Open) Then
                myCon.close()
            End If
        End Try
    End Sub
End Module
