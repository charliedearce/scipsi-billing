Public Class frmor

    Private Sub PictureBox1_Click(sender As Object, e As EventArgs)

    End Sub



    Private Sub txtOrno_LostFocus(sender As Object, e As EventArgs) Handles txtOrno.LostFocus
        On Error Resume Next
        Dim change As Integer = txtOrno.Text
        txtOrno.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))


    End Sub



    Private Sub txtAccountno_LostFocus(sender As Object, e As EventArgs) Handles txtAccountno.LostFocus
        If btnAddOr.Enabled = False Then
        Else
            vORAccno()
        End If

        If btnUpdate.Visible = True Then
            vORAccno()
        End If

    End Sub

    Private Sub frmOR_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown

        If (e.KeyCode = Keys.F2) And btnAddOr.Enabled = True Then
            btnAddOr_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F1) And btnNewOR.Enabled = True Then
            btnNewOR_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F3) And btnDeleteOr.Enabled = True Then
            btnDeleteOr_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F4) And btnCashtendered.Enabled = True Then
            btnCashtendered_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F5) And btnDeposit.Enabled = True Then
            btnDeposit_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F6) And btnEdit.Visible = True Then
            btnEdit_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F7) And btnUpdate.Visible = True Then
            btnUpdate_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F8) And btnpartial.Enabled = True Then
            btnpartial_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F10) And btnPrintOr.Enabled = True Then
            btnPrintOr_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F11) And btnSearchOr.Enabled = True Then
            btnSearchOr_Click(sender, e)
        ElseIf (e.KeyCode = Keys.F12) And btnOrReport.Enabled = True Then
            btnOrReport_Click(sender, e)
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

    Private Sub frmOR_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        bank()
        Me.KeyPreview = True
        viewORItems()
    End Sub



    Private Sub txtNetIncome_TextChanged(sender As Object, e As EventArgs) Handles txtNetIncome.TextChanged
        NumberFormat(txtNetIncome)

    End Sub

    Private Sub btnDeleteOr_Click(sender As Object, e As EventArgs) Handles btnDeleteOr.Click
        frmORBillDelete.ShowDialog()
    End Sub

    Private Sub txtORVat_TextChanged(sender As Object, e As EventArgs) Handles txtORVat.TextChanged
        NumberFormat(txtORVat)
    End Sub

    Private Sub txtORBillAmount_TextChanged(sender As Object, e As EventArgs) Handles txtORBillAmount.TextChanged
        NumberFormat(txtORBillAmount)
    End Sub

    Private Sub txtHTax_TextChanged(sender As Object, e As EventArgs) Handles txtHTax.TextChanged
        NumberFormat(txtHTax)

    End Sub

    Private Sub btnCashtendered_Click(sender As Object, e As EventArgs) Handles btnCashtendered.Click
        frmORCash.ShowDialog()
    End Sub

    Private Sub btnNewOR_Click(sender As Object, e As EventArgs) Handles btnNewOR.Click
        btnDeleteOr.Enabled = False
        lbldatetrans.Enabled = False
        btnEdit.Visible = False
        btnUpdate.Visible = False
        txtSearchIndicator.Text = 0
        txtbal.Text = 0
        btnDeposit.Enabled = True
        btnNewOR.Enabled = False
        txtOrno.Enabled = True
        txtOrno.Focus()
        txtAccountno.Enabled = True
        txtRemarks.Enabled = True
        btnAddOr.Enabled = True
        btnpartial.Enabled = True
        txtbank.Enabled = True
        btnPrintOr.Enabled = False
        txtRemarks.Enabled = True
        citw.Enabled = True
        citw.CheckState = False

        lbldatetrans.Text = ""
        txtOrno.Text = ""
        txtAccountno.Text = ""
        lblAccname.Text = ""
        txtRemarks.Text = ""
        txtbank.Text = ""
        txtORVat.Text = 0
        txtORBillAmount.Text = 0
        txtHTax.Text = 0
        txtNetIncome.Text = 0
        viewORItems()
        txtNotice.Text = ""
        txtBstyle.Text = ""
        lblTin.Text = ""
        lblAddress.Text = ""

    End Sub


    Private Sub btnSearchOr_Click(sender As Object, e As EventArgs) Handles btnSearchOr.Click
        labelstatus.Text = 0
        txtSearchIndicator.Text = 1
        frmORSearchvb.ShowDialog()
    End Sub

    Private Sub btnPrintOr_Click(sender As Object, e As EventArgs) Handles btnPrintOr.Click

        txtSearchIndicator.Text = 1

        frmPrintOR.txtCheck.Text = txtCheckno.Text
        frmPrintOR.lblVat.Text = txtORVat.Text
        frmPrintOR.lblAmt.Text = txtORBillAmount.Text
        frmPrintOR.lblTax.Text = txtHTax.Text
        frmPrintOR.lblTotal.Text = txtNetIncome.Text


        frmPrintOR.txtword.Text = SpellNumber(txtNetIncome.Text)
        frmPrintOR.lblval.Text = txtNetIncome.Text

        frmPrintOR.lblclient.Text = lblAccname.Text

        'NEW OR FIELDS
        'frmPrintOR.lblAddress.Text = lblAddress.Text
        'frmPrintOR.lblTin.Text = lblTin.Text
        'frmPrintOR.lblBstyle.Text = txtBstyle.Text

        Dim result As Integer = MessageBox.Show("You want to Print this transaction?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            btnUpdate.Visible = False
            btnEdit.Visible = False
            frmPrintOR.Show()
        End If
    End Sub

    Private Sub txtCash_TextChanged(sender As Object, e As EventArgs) Handles txtCash.TextChanged
        NumberFormat(txtCash)
    End Sub

    Private Sub txtChange_TextChanged(sender As Object, e As EventArgs) Handles txtChange.TextChanged
        NumberFormat(txtChange)
    End Sub

    Private Sub btnOrReport_Click(sender As Object, e As EventArgs) Handles btnOrReport.Click
        ORMonthSelectionReport.ShowDialog()
    End Sub

    Private Sub txtOrno_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtOrno.MaskInputRejected

    End Sub

    Private Sub txtOrno_ModifiedChanged(sender As Object, e As EventArgs) Handles txtOrno.ModifiedChanged

    End Sub

    Private Sub txtOrno_TextChanged(sender As Object, e As EventArgs) Handles txtOrno.TextChanged
        If txtSearchIndicator.Text = 0 Then
            vORno()
        Else
        End If
    End Sub



    Private Sub lblAccname_Click(sender As Object, e As EventArgs) Handles lblAccname.Click
        If labelstatus.Text = 1 Then
            frmWalkin1.txtSearch.Text = lblAccname.Text
            frmWalkin1.ShowDialog()
        Else
        End If
    End Sub

    Private Sub btnAddOr_Click(sender As Object, e As EventArgs) Handles btnAddOr.Click
        If txtAccountno.Text = "     -" Or txtbank.Text = "" Or txtOrno.Text = "" Then
            txtNotice.Text = "Please Fill Up important fields in the form."
        Else

            frmORBillno.ShowDialog()


            'btnDeposit.Enabled = False
        End If
    End Sub

    Private Sub txtDiscount_TextChanged(sender As Object, e As EventArgs) Handles txtDiscount.TextChanged
        NumberFormat(txtDiscount)
    End Sub


    Private Sub txtAccountno_TextChanged(sender As Object, e As EventArgs) Handles txtAccountno.TextChanged
        If txtAccountno.Text = "11001-0000" Then
            labelstatus.Text = 1
        Else
            labelstatus.Text = 0
        End If

    End Sub

    Private Sub dgOrItems_CellContentClick(sender As Object, e As DataGridViewCellEventArgs)

    End Sub

    Private Sub dgOrItems_CellDoubleClick(sender As Object, e As DataGridViewCellEventArgs)

    End Sub

    Private Sub dgOrItems_SelectionChanged(sender As Object, e As EventArgs)


    End Sub

    Private Sub btnDeposit_Click(sender As Object, e As EventArgs) Handles btnDeposit.Click
        If txtAccountno.Text = "     -" Or txtbank.Text = "" Or txtOrno.Text = "" Then
            txtNotice.Text = "Please Fill Up important fields in the form."
        Else
            frmDeposit.ShowDialog()
            'btnAddOr.Enabled = False
        End If
    End Sub


    Private Sub txtRemarks_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtRemarks.KeyPress
        If Asc(e.KeyChar) <> 8 And Asc(e.KeyChar) <> 32 And Asc(e.KeyChar) <> 35 And Asc(e.KeyChar) <> 46 And Asc(e.KeyChar) <> 47 And Asc(e.KeyChar) <> 40 And Asc(e.KeyChar) <> 41 And Asc(e.KeyChar) <> 37 And Asc(e.KeyChar) <> 38 And Asc(e.KeyChar) <> 36 And Asc(e.KeyChar) <> 60 And Asc(e.KeyChar) <> 61 And Asc(e.KeyChar) <> 62 And Asc(e.KeyChar) <> 63 And Asc(e.KeyChar) <> 45 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 65 Or Asc(e.KeyChar) > 90) And (Asc(e.KeyChar) < 97 Or Asc(e.KeyChar) > 122) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtRemarks_TextChanged(sender As Object, e As EventArgs) Handles txtRemarks.TextChanged

    End Sub

    Private Sub txtbal_TextChanged(sender As Object, e As EventArgs) Handles txtbal.TextChanged
        NumberFormat(txtbal)
    End Sub
    Private Sub btnEdit_Click(sender As Object, e As EventArgs) Handles btnEdit.Click
        txtSearchIndicator.Text = 0
        btnPrintOr.Enabled = False
        btnSearchOr.Enabled = False
        btnNewOR.Enabled = False
        txtAccountno.Enabled = True
        txtbank.Enabled = True
        txtRemarks.Enabled = True
        lbldatetrans.Enabled = True
        btnEdit.Visible = False
        btnUpdate.Visible = True

    End Sub

    Private Sub btnUpdate_Click(sender As Object, e As EventArgs) Handles btnUpdate.Click
        If txtAccountno.Text = "     -" Or txtbank.Text = "" Or txtOrno.Text = "" Then
            txtNotice.Text = "Please Fill Up important fields in the form."
        Else
            Dim result As Integer = MessageBox.Show("Are you sure you want to UPDATE this transaction?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                updateOR()
                btnPrintOr.Enabled = True
                btnSearchOr.Enabled = True

                txtOrno.Enabled = False
                txtAccountno.Enabled = False
                txtRemarks.Enabled = False
                btnAddOr.Enabled = False
                btnpartial.Enabled = False
                txtbank.Enabled = False
                btnDeposit.Enabled = False
                btnNewOR.Enabled = True
                btnUpdate.Visible = False
                lbldatetrans.Enabled = False
                txtSearchIndicator.Text = 1
            End If
        End If
    End Sub

    Private Sub txtbank_KeyPress(sender As Object, e As KeyPressEventArgs)

    End Sub

    Private Sub txtbank_TextChanged(sender As Object, e As EventArgs)

    End Sub

    Private Sub dgOrItems_CellContentClick_1(sender As Object, e As DataGridViewCellEventArgs) Handles dgOrItems.CellContentClick

    End Sub

    Private Sub dgOrItems_CellContentDoubleClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgOrItems.CellContentDoubleClick

        If txtSearchIndicator.Text = 1 Then
        Else
            frmOREdit.ShowDialog()
            frmOREdit.txtid.Text = dgOrItems.CurrentRow.Cells(0).Value.ToString
        End If
    End Sub

    Private Sub dgOrItems_SelectionChanged1(sender As Object, e As EventArgs) Handles dgOrItems.SelectionChanged
        On Error Resume Next
        txtbillno.Text = dgOrItems.CurrentRow.Cells(1).Value.ToString
        txtamt.Text = dgOrItems.CurrentRow.Cells(3).Value.ToString
        txtholdtax.Text = dgOrItems.CurrentRow.Cells(4).Value.ToString
        txtdisc.Text = dgOrItems.CurrentRow.Cells(5).Value.ToString
        frmOREdit.txtid.Text = dgOrItems.CurrentRow.Cells(0).Value.ToString
    End Sub


    Private Sub SimpleButton2_Click(sender As Object, e As EventArgs) Handles btnBack.Click
        txtOrno.Focus()
        txtSearchIndicator.Text = 0
        btnNewOR.Enabled = True
        btnSearchOr.Enabled = True
        txtOrno.Enabled = False

        txtAccountno.Enabled = False
        txtRemarks.Enabled = False

        lbldatetrans.Text = ""
        txtOrno.Text = ""
        txtAccountno.Text = ""
        lblAccname.Text = ""
        txtRemarks.Text = ""
        txtbank.Text = ""
        txtORVat.Text = 0
        txtORBillAmount.Text = 0
        txtHTax.Text = 0
        txtNetIncome.Text = 0
        'dgOrItems.DataSource = ""
        viewORItems()
        txtNotice.Text = ""

        lblTin.Text = ""
        lblAddress.Text = ""
        txtBstyle.Text = ""

        Me.Dispose()
        frmBilling.Show()
    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        Me.WindowState = FormWindowState.Minimized
    End Sub

    Private Sub citw_CheckedChanged(sender As Object, e As EventArgs) Handles citw.CheckedChanged

    End Sub

    Private Sub citw_GotFocus(sender As Object, e As EventArgs) Handles citw.GotFocus
        citw.BackColor = Color.DeepSkyBlue
    End Sub

    Private Sub citw_LostFocus(sender As Object, e As EventArgs) Handles citw.LostFocus
        citw.BackColor = Color.Transparent
    End Sub

    Private Sub btnpartial_Click(sender As Object, e As EventArgs) Handles btnpartial.Click
        If txtAccountno.Text = "     -" Or txtbank.Text = "" Or txtOrno.Text = "" Then
            txtNotice.Text = "Please Fill Up important fields in the form."
        Else
            frmPartial.ShowDialog()
        End If
    End Sub

    Private Sub txtAccountno_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs) Handles txtAccountno.MaskInputRejected

    End Sub

    Private Sub Timer1_Tick(sender As Object, e As EventArgs) Handles forsysdate.Tick
        systemtime(txtTime.Text)
    End Sub


    Private Sub OfficialReceiptToolStripMenuItem_Click(sender As Object, e As EventArgs) Handles OfficialReceiptToolStripMenuItem.Click
        If lbldatetrans.Text = "" Then
            MsgBox("Please Search OR to Request.", vbInformation, "Message")
        Else
            frmorrequest.ShowDialog()
        End If

    End Sub

    Private Sub Timer1_Tick_1(sender As Object, e As EventArgs)

    End Sub

    Private Sub Timer1_Tick_2(sender As Object, e As EventArgs) Handles Timer1.Tick
        autochangedate(txtTime.Text)
    End Sub
End Class