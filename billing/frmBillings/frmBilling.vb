Imports System
Imports System.Globalization
Imports System.Windows.Forms
Imports AutoUpdaterDotNET
Public Class frmBilling

    Private Sub frmBilling_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        'If (e.Alt AndAlso (e.KeyCode = Keys.P)) Then
        ' When Alt + P is pressed, the Click event for the print
        ' button is raised.
        'RaiseEvent btnPrintCustomerDetails.Click
        'End If

        If (e.KeyCode = Keys.F2) And btnAddItem.Enabled = True Then
            btnAddItem_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F1) And btnUpdate.Enabled = True Then
            btnNewBill_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F3) And btnDeleteItem.Enabled = True Then
            btnDeleteItem_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F4) And btnSave.Enabled = True Then
            btnSave_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F5) And btnEdit.Visible = True Then
            btnEdit_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F6) And btnUpdate.Visible = True Then
            btnUpdate_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F8) And btnPrint.Enabled = True Then
            btnPrint_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F9) And btnSearch.Enabled = True Then
            btnSearch_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F10) And btnReport.Enabled = True Then
            btnOr_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F11) And btnReport.Enabled = True Then
            btnReport_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F12) And btnReport.Enabled = True Then
            btnOut_Click(sender, e)
        End If


    End Sub
    Protected Overrides ReadOnly Property CreateParams() As CreateParams
        Get
            Dim cp As CreateParams = MyBase.CreateParams
            Const CS_NOCLOSE As Integer = &H200
            cp.ClassStyle = cp.ClassStyle Or CS_NOCLOSE
            Return cp
        End Get
    End Property

    Private Sub frmBilling_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        AutoUpdater.Start("http://192.168.10.29/update/update.xml")

        Me.KeyPreview = True
        cbRoute.Text = "Domestic"
        cbType.Text = "IN"


    End Sub


    Private Sub btnAddItem_Click(sender As Object, e As EventArgs) Handles btnAddItem.Click
        vAccno()
        'autoService()
        autoCargo()
        If txtBillno.Text = "" Or txtAccountno.Text = "     -" Or txtVessel.Text = "" Or txtVoyage.Text = "" Then
            txtNotice.Text = "Please Fill up important data in the form."
        Else
            frmBilladd.ShowDialog()
        End If

    End Sub

    Private Sub txtBillno_GotFocus(sender As Object, e As EventArgs) Handles txtBillno.GotFocus
        'myCon.close()
    End Sub





    Private Sub txtBillno_LostFocus(sender As Object, e As EventArgs) Handles txtBillno.LostFocus
        On Error Resume Next
        Dim change As Integer = txtBillno.Text
        txtBillno.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))
        vBillno()
        billnostatus1()
    End Sub

    Private Sub txtAccountno_Layout(sender As Object, e As LayoutEventArgs) Handles txtAccountno.Layout

    End Sub



    Private Sub txtAccountno_LostFocus(sender As Object, e As EventArgs) Handles txtAccountno.LostFocus
        If btnUpdate.Enabled = True Then
            vAccno()
        End If
        If btnAddItem.Enabled = False Then
        Else
            vAccno()
        End If
    End Sub



    Private Sub txtVessel_GotFocus(sender As Object, e As EventArgs) Handles txtVessel.GotFocus

    End Sub


    Private Sub txtTotal_TextChanged(sender As Object, e As EventArgs) Handles txtTotal.TextChanged
        NumberFormat(txtTotal)
    End Sub

    Private Sub txtLessppa_TextChanged(sender As Object, e As EventArgs) Handles txtLessppa.TextChanged
        NumberFormat(txtLessppa)
    End Sub

    Private Sub txtNet_TextChanged(sender As Object, e As EventArgs) Handles txtNet.TextChanged
        NumberFormat(txtNet)
    End Sub

    Private Sub txtVat_TextChanged(sender As Object, e As EventArgs) Handles txtVat.TextChanged
        NumberFormat(txtVat)
    End Sub

    Private Sub txtScipsi_TextChanged(sender As Object, e As EventArgs) Handles txtScipsi.TextChanged
        NumberFormat(txtScipsi)
    End Sub
    Private Sub btnDeleteItem_Click(sender As Object, e As EventArgs) Handles btnDeleteItem.Click
        frmBillingDelete.ShowDialog()
    End Sub




    Private Sub ppa_Click(sender As Object, e As EventArgs) Handles ppa.Click
        If txtIndicator.Text = 0 Then
            viewItem()
        End If
    End Sub

    Private Sub vat_Click(sender As Object, e As EventArgs) Handles vat.Click
        If txtIndicator.Text = 0 Then
            viewItem()
        End If
    End Sub

    Private Sub cbRoute_SelectedValueChanged(sender As Object, e As EventArgs) Handles cbRoute.SelectedValueChanged
        If txtIndicator.Text = 0 Then
            viewItem()
        End If
    End Sub

    Private Sub btnPrint_Click(sender As Object, e As EventArgs) Handles btnPrint.Click

        Dim result As Integer = MessageBox.Show("You want to Print this transaction?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            If txtLessppa.Text <> "0.00" Then
                frmPrintBill.Show()
                btnUpdate.Visible = False
                btnEdit.Visible = False
            Else
                frmPrintService.Show()
                btnUpdate.Visible = False
                btnEdit.Visible = False
            End If
        End If
    End Sub


    Private Sub btnNewBill_Click(sender As Object, e As EventArgs) Handles btnNewBill.Click
        txtName.Text = txtnametemp.Text
        btnNewBill.Enabled = False
        btnEdit.Visible = False
        btnUpdate.Visible = False
        btnNewBill.Enabled = False
        vat.CheckState = CheckState.Checked
        ppa.CheckState = CheckState.Checked
        labelstatus.Text = 0
        txtSearchIndicator.Text = 0
        txtIndicator.Text = 0
        btnPrint.Enabled = False
        btnUpdate.Enabled = False
        btnAddItem.Enabled = True
        txtBillno.Enabled = True
        txtBillno.Focus()
        txtAccountno.Enabled = True
        txtParticulars.Enabled = True
        txtVoyage.Enabled = True
        txtVessel.Enabled = True
        cbRoute.Enabled = True
        cbType.Enabled = True
        ppa.Enabled = True
        vat.Enabled = True
        ' citw.Enabled = True

        lbldatetrans.Text = ""
        txtBillno.Text = ""
        txtAccountno.Text = ""
        lblAccname.Text = ""
        txtVessel.Text = ""
        txtVoyage.Text = ""
        txtParticulars.Text = ""
        txtBstyle.Text = ""
        lblTin.Text = ""
        lblAddress.Text = ""

        txtTotal.Text = 0
        txtLessppa.Text = 0
        txtNet.Text = 0
        txtVat.Text = 0
        'txtctiw.Text = 0
        txtScipsi.Text = 0
        viewItem()

    End Sub





    Private Sub btnSave_Click(sender As Object, e As EventArgs) Handles btnSave.Click
        If txtBillno.Text = "" Or txtAccountno.Text = "     -" Or txtVessel.Text = "" Or txtVoyage.Text = "" Or lblAccname.Text = "" Then
            txtNotice.Text = "Please Fill up important data in the form."
        Else
            Dim result As Integer = MessageBox.Show("Are you sure you want to Save this transaction?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then

                txtSearchIndicator.Text = 1
                saveBill()
                btnPrint_Click(sender, e)
                btnOut.Enabled = True
                btnSearch.Enabled = True
                btnUpdate.Enabled = True
                btnAddItem.Enabled = False
                txtBillno.Enabled = False
                txtAccountno.Enabled = False
                txtParticulars.Enabled = False
                txtVoyage.Enabled = False
                txtVessel.Enabled = False
                cbRoute.Enabled = False
                cbType.Enabled = False
                ppa.Enabled = False
                vat.Enabled = False
                ' citw.Enabled = False
                btnDeleteItem.Enabled = False
                btnSave.Enabled = False

                txtBillno.Text = ""
                txtAccountno.Text = ""
                lblAccname.Text = ""
                txtVessel.Text = ""
                txtVoyage.Text = ""
                txtParticulars.Text = ""
                txtBstyle.Text = ""
                lblTin.Text = ""
                lblAddress.Text = ""

                txtTotal.Text = 0
                txtLessppa.Text = 0
                txtNet.Text = 0
                txtVat.Text = 0
                'txtctiw.Text = 0
                txtScipsi.Text = 0


                viewItem()
            End If
        End If
    End Sub

    Private Sub btnSearch_Click(sender As Object, e As EventArgs) Handles btnSearch.Click
        labelstatus.Text = 0
        txtIndicator.Text = 1
        txtSearchIndicator.Text = 1
        frmBillSearch.ShowDialog()
        vAccno()
    End Sub



    Private Sub btnReport_Click(sender As Object, e As EventArgs) Handles btnReport.Click
        BillMonthSelectionReport.ShowDialog()
    End Sub

    Private Sub ppa_CheckedChanged(sender As Object, e As EventArgs) Handles ppa.CheckedChanged

    End Sub

    Private Sub citw_CheckedChanged(sender As Object, e As EventArgs)

    End Sub

    Private Sub cbRoute_SelectedIndexChanged(sender As Object, e As EventArgs) Handles cbRoute.SelectedIndexChanged

    End Sub

    Private Sub btnOr_Click(sender As Object, e As EventArgs) Handles btnOr.Click
        Me.Hide()
        frmor.ShowDialog()
    End Sub


    Private Sub GroupBox1_Enter(sender As Object, e As EventArgs) Handles GroupBox1.Enter

    End Sub

    Private Sub txtctiw_TextChanged(sender As Object, e As EventArgs)
        'NumberFormat(txtctiw)
    End Sub



    Private Sub txtBillno_TextChanged(sender As Object, e As EventArgs) Handles txtBillno.TextChanged

        If txtSearchIndicator.Text = 0 Then
            billnostatus()

        Else
        End If


    End Sub


    Private Sub txtBillno_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtBillno.MaskInputRejected

    End Sub

    Private Sub btnOut_Click(sender As Object, e As EventArgs) Handles btnOut.Click
        systemtime(sysdate)
        Dim result As Integer = MessageBox.Show("Are you sure you want to Log out?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            singinlogs("Login", txtnametemp.Text + " Has signed out | Time: " + sysdate)
            txtnametemp.Text = ""
            txtBillno.Text = ""
            txtAccountno.Text = ""
            lblAccname.Text = ""
            txtName.Text = ""
            txtVessel.Text = ""
            txtVoyage.Text = ""
            txtParticulars.Text = ""
            viewItem()
            btnUpdate.Enabled = True
            With frmor
                .txtOrno.Text = ""
                .txtAccountno.Text = ""
                .lblAccname.Text = ""
                .txtRemarks.Text = ""
                .txtBstyle.Text = ""
                .lblTin.Text = ""
                .lblAddress.Text = ""

                '.dgOrItems.DataSource = ""
                viewORItems()
                .btnNewOR.Enabled = True
            End With

            With frmLogin
                .txtUname.Text = "Username"
                .txtPass.Text = "Password"
                .txtUname.Focus()
            End With

            Me.Dispose()
            frmLogin.Show()
        End If
    End Sub



    Private Sub txtAccountno_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtAccountno.MaskInputRejected

    End Sub



    Private Sub lblAccname_Click(sender As Object, e As EventArgs) Handles lblAccname.Click
        If labelstatus.Text = 1 Then
            frmWalkin.txtSearch.Text = lblAccname.Text
            frmWalkin.ShowDialog()
        Else

        End If
    End Sub



    Private Sub txtAccountno_TextChanged(sender As Object, e As EventArgs) Handles txtAccountno.TextChanged
        If txtAccountno.Text = "11001-0000" Then
            labelstatus.Text = 1
        Else
            labelstatus.Text = 0
        End If
    End Sub



    Private Sub txtDiscount_TextChanged(sender As Object, e As EventArgs) Handles txtDiscount.TextChanged
        NumberFormat(txtDiscount)
    End Sub



    Private Sub txtParticulars_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtParticulars.KeyPress
        If Asc(e.KeyChar) <> 8 And Asc(e.KeyChar) <> 32 And Asc(e.KeyChar) <> 35 And Asc(e.KeyChar) <> 46 And Asc(e.KeyChar) <> 47 And Asc(e.KeyChar) <> 40 And Asc(e.KeyChar) <> 41 And Asc(e.KeyChar) <> 37 And Asc(e.KeyChar) <> 38 And Asc(e.KeyChar) <> 36 And Asc(e.KeyChar) <> 60 And Asc(e.KeyChar) <> 61 And Asc(e.KeyChar) <> 62 And Asc(e.KeyChar) <> 63 And Asc(e.KeyChar) <> 45 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 65 Or Asc(e.KeyChar) > 90) And (Asc(e.KeyChar) < 97 Or Asc(e.KeyChar) > 122) Then
                e.Handled = True
            End If

        End If
    End Sub



    Private Sub btnEdit_Click(sender As Object, e As EventArgs) Handles btnEdit.Click
        btnPrint.Enabled = False
        btnSearch.Enabled = False
        txtAccountno.Enabled = True
        txtVoyage.Enabled = True
        txtVessel.Enabled = True
        txtParticulars.Enabled = True
        cbType.Enabled = True
        ppa.Enabled = True
        vat.Enabled = True
        lbldatetrans.Enabled = True
        btnUpdate.Visible = True
        btnEdit.Visible = False
    End Sub

    Private Sub btnUpdate_Click(sender As Object, e As EventArgs) Handles btnUpdate.Click
        If txtBillno.Text = "" Or txtAccountno.Text = "     -" Or lbldatetrans.Text = "  /  /" Or txtVessel.Text = "" Or txtVoyage.Text = "" Or lblAccname.Text = "" Then
            txtNotice.Text = "Please Fill up important data in the form."
        Else
            Dim result As Integer = MessageBox.Show("Are you sure you want to UPDATE this transaction?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                updateBill()
                txtSearchIndicator.Text = 1
                btnPrint.Enabled = True
                btnSearch.Enabled = True
                btnOut.Enabled = True
                btnSearch.Enabled = True
                btnUpdate.Enabled = True
                btnAddItem.Enabled = False
                txtBillno.Enabled = False
                txtAccountno.Enabled = False
                txtParticulars.Enabled = False
                txtVoyage.Enabled = False
                txtVessel.Enabled = False
                cbRoute.Enabled = False
                cbType.Enabled = False
                ppa.Enabled = False
                vat.Enabled = False
                lbldatetrans.Enabled = False
                btnDeleteItem.Enabled = False
                btnSave.Enabled = False

                btnUpdate.Visible = False

            End If
        End If
    End Sub




    Private Sub BillingStatementToolStripMenuItem1_Click(sender As Object, e As EventArgs) Handles BillingStatementToolStripMenuItem1.Click
        frmStatement.ShowDialog()
    End Sub


    Private Sub RTCToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles RTCToolStripMenuItem.Click
        frmRTC.ShowDialog()
    End Sub

    Private Sub txtVoyage_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtVoyage.MaskInputRejected

    End Sub


  
    Private Sub UpdateSoftwareToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles UpdateSoftwareToolStripMenuItem.Click
        AutoUpdater.Start("http://192.168.10.29/update/update.xml")
    End Sub

    Private Sub YellowBillsToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles YellowBillsToolStripMenuItem.Click
        frmYtrans.ShowDialog()
    End Sub

    Private Sub WhiteBillsToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles WhiteBillsToolStripMenuItem.Click
        frmWtrans.ShowDialog()
    End Sub

    Private Sub ToolStripMenuItem1_Click(sender As Object, e As EventArgs) Handles ToolStripMenuItem1.Click

    End Sub

    Private Sub AboutToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles AboutToolStripMenuItem.Click
        About.ShowDialog()
    End Sub

    Private Sub dgItems_CellContentClick_1(sender As Object, e As DataGridViewCellEventArgs) Handles dgItems.CellContentClick

    End Sub

    Private Sub ppa_GotFocus(sender As Object, e As EventArgs) Handles ppa.GotFocus
        ppa.BackColor = Color.DeepSkyBlue
    End Sub

    Private Sub ppa_LostFocus(sender As Object, e As EventArgs) Handles ppa.LostFocus
        ppa.BackColor = Color.Transparent
    End Sub

    Private Sub vat_GotFocus(sender As Object, e As EventArgs) Handles vat.GotFocus
        vat.BackColor = Color.DeepSkyBlue
    End Sub

    Private Sub vat_LostFocus(sender As Object, e As EventArgs) Handles vat.LostFocus
        vat.BackColor = Color.Transparent
    End Sub

    Private Sub GroupBox2_Enter(sender As Object, e As EventArgs) Handles GroupBox2.Enter

    End Sub

    Private Sub Label16_Click(sender As Object, e As EventArgs) Handles Label16.Click

    End Sub

    Private Sub txtnumber_TextChanged(sender As Object, e As EventArgs)

    End Sub

    Private Sub Label17_Click(sender As Object, e As EventArgs)

    End Sub

    Private Sub CustomerToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles CustomerToolStripMenuItem.Click
        SearchAcc.ShowDialog()
    End Sub

    Private Sub Label17_Click_1(sender As Object, e As EventArgs)

    End Sub
    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        Me.WindowState = FormWindowState.Minimized
    End Sub

    Private Sub PaidCustomerToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles PaidCustomerToolStripMenuItem.Click
        searchPaid.ShowDialog()
    End Sub

    Private Sub MaskedTextBox1_LostFocus(sender As Object, e As EventArgs) Handles MaskedTextBox1.LostFocus

    End Sub

    Private Sub MaskedTextBox1_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles MaskedTextBox1.MaskInputRejected

    End Sub

    Private Sub MenuStrip1_ItemClicked(sender As Object, e As ToolStripItemClickedEventArgs) Handles MenuStrip1.ItemClicked

    End Sub

    Private Sub Timer1_Tick(sender As Object, e As EventArgs) Handles forsysdate.Tick
        systemtime(txtTime.Text)

    End Sub

    Private Sub lbldatetrans_EditValueChanged(sender As Object, e As EventArgs) Handles lbldatetrans.EditValueChanged

    End Sub

    Private Sub Label10_Click(sender As Object, e As EventArgs) Handles Label10.Click

    End Sub

    Private Sub Label5_Click(sender As Object, e As EventArgs) Handles Label5.Click

    End Sub

    Private Sub txtTime_Click(sender As Object, e As EventArgs) Handles txtTime.Click

    End Sub

    Private Sub BackDatesToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles BackDatesToolStripMenuItem.Click
        If BackDatesToolStripMenuItem.Text = "Back Dates" Then
            frmBackdates.ShowDialog()
        ElseIf BackDatesToolStripMenuItem.Text = "Restore Date" Then
            restoredate()
        End If
    End Sub

    Private Sub fordatereq_Tick(sender As Object, e As EventArgs) Handles fordatereq.Tick
        autochangedate(txtTime.Text)
    End Sub

    Private Sub BillTransactionsToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles BillTransactionsToolStripMenuItem.Click
        If lbldatetrans.Text = "" Then
            MsgBox("Please Search Invoice to Request.", vbInformation, "Message")
        Else
            frmbillrequest.ShowDialog()
        End If
    End Sub

    Private Sub vat_CheckedChanged(sender As Object, e As EventArgs) Handles vat.CheckedChanged
        If txtSearchIndicator.Text = 1 Then
        Else
            If cbRoute.Text = "FOREIGN" Then
                Dim result As Integer = MessageBox.Show("Are you sure you warnt to apply vat in this foreign transaction?", "Caution!", MessageBoxButtons.YesNo)
                If result = DialogResult.Yes Then
                    vat.Checked = True
                Else
                    vat.Checked = False
                End If
            End If
        End If

    End Sub

    Private Sub ChangePasswordToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles ChangePasswordToolStripMenuItem.Click
        frmchangepass.ShowDialog()
    End Sub
End Class