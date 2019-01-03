<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmPrintService
    Inherits System.Windows.Forms.Form

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        Try
            If disposing AndAlso components IsNot Nothing Then
                components.Dispose()
            End If
        Finally
            MyBase.Dispose(disposing)
        End Try
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Dim DataGridViewCellStyle1 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle2 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle3 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle4 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Me.Panel1 = New System.Windows.Forms.Panel()
        Me.grossbox = New System.Windows.Forms.ListBox()
        Me.lblParticulars = New System.Windows.Forms.Label()
        Me.ratebox = New System.Windows.Forms.ListBox()
        Me.lbldiscount = New System.Windows.Forms.Label()
        Me.cargobox = New System.Windows.Forms.ListBox()
        Me.Label7 = New System.Windows.Forms.Label()
        Me.servicebox = New System.Windows.Forms.ListBox()
        Me.unitbox = New System.Windows.Forms.ListBox()
        Me.lblduescipsi = New System.Windows.Forms.Label()
        Me.qtybox = New System.Windows.Forms.ListBox()
        Me.lblppa = New System.Windows.Forms.Label()
        Me.lblvat = New System.Windows.Forms.Label()
        Me.lblscipsi = New System.Windows.Forms.Label()
        Me.lbltotal = New System.Windows.Forms.Label()
        Me.Label15 = New System.Windows.Forms.Label()
        Me.Label14 = New System.Windows.Forms.Label()
        Me.Label13 = New System.Windows.Forms.Label()
        Me.Label12 = New System.Windows.Forms.Label()
        Me.lblBill = New System.Windows.Forms.Label()
        Me.lblcleintname = New System.Windows.Forms.Label()
        Me.lbltime = New System.Windows.Forms.Label()
        Me.lblBillingname = New System.Windows.Forms.Label()
        Me.Label5 = New System.Windows.Forms.Label()
        Me.DataGridPrint = New System.Windows.Forms.DataGridView()
        Me.Column1 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column2 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column3 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column4 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column5 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.Column6 = New System.Windows.Forms.DataGridViewTextBoxColumn()
        Me.PrintDocument1 = New System.Drawing.Printing.PrintDocument()
        Me.cargodesc = New System.Windows.Forms.ListBox()
        Me.Panel1.SuspendLayout()
        CType(Me.DataGridPrint, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.SuspendLayout()
        '
        'Panel1
        '
        Me.Panel1.BackColor = System.Drawing.Color.White
        Me.Panel1.BackgroundImageLayout = System.Windows.Forms.ImageLayout.Stretch
        Me.Panel1.Controls.Add(Me.cargodesc)
        Me.Panel1.Controls.Add(Me.grossbox)
        Me.Panel1.Controls.Add(Me.lblParticulars)
        Me.Panel1.Controls.Add(Me.ratebox)
        Me.Panel1.Controls.Add(Me.lbldiscount)
        Me.Panel1.Controls.Add(Me.cargobox)
        Me.Panel1.Controls.Add(Me.Label7)
        Me.Panel1.Controls.Add(Me.servicebox)
        Me.Panel1.Controls.Add(Me.unitbox)
        Me.Panel1.Controls.Add(Me.lblduescipsi)
        Me.Panel1.Controls.Add(Me.qtybox)
        Me.Panel1.Controls.Add(Me.lblppa)
        Me.Panel1.Controls.Add(Me.lblvat)
        Me.Panel1.Controls.Add(Me.lblscipsi)
        Me.Panel1.Controls.Add(Me.lbltotal)
        Me.Panel1.Controls.Add(Me.Label15)
        Me.Panel1.Controls.Add(Me.Label14)
        Me.Panel1.Controls.Add(Me.Label13)
        Me.Panel1.Controls.Add(Me.Label12)
        Me.Panel1.Controls.Add(Me.lblBill)
        Me.Panel1.Controls.Add(Me.lblcleintname)
        Me.Panel1.Controls.Add(Me.lbltime)
        Me.Panel1.Controls.Add(Me.lblBillingname)
        Me.Panel1.Controls.Add(Me.Label5)
        Me.Panel1.Controls.Add(Me.DataGridPrint)
        Me.Panel1.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Panel1.Location = New System.Drawing.Point(2, 1)
        Me.Panel1.Name = "Panel1"
        Me.Panel1.Size = New System.Drawing.Size(816, 624)
        Me.Panel1.TabIndex = 2
        '
        'grossbox
        '
        Me.grossbox.FormattingEnabled = True
        Me.grossbox.Location = New System.Drawing.Point(673, 242)
        Me.grossbox.Name = "grossbox"
        Me.grossbox.Size = New System.Drawing.Size(120, 95)
        Me.grossbox.TabIndex = 62
        '
        'lblParticulars
        '
        Me.lblParticulars.AutoSize = True
        Me.lblParticulars.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblParticulars.Location = New System.Drawing.Point(518, 183)
        Me.lblParticulars.Name = "lblParticulars"
        Me.lblParticulars.Size = New System.Drawing.Size(0, 13)
        Me.lblParticulars.TabIndex = 54
        '
        'ratebox
        '
        Me.ratebox.FormattingEnabled = True
        Me.ratebox.Location = New System.Drawing.Point(547, 244)
        Me.ratebox.Name = "ratebox"
        Me.ratebox.Size = New System.Drawing.Size(120, 95)
        Me.ratebox.TabIndex = 61
        '
        'lbldiscount
        '
        Me.lbldiscount.AutoSize = True
        Me.lbldiscount.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbldiscount.Location = New System.Drawing.Point(306, 398)
        Me.lbldiscount.Name = "lbldiscount"
        Me.lbldiscount.Size = New System.Drawing.Size(89, 13)
        Me.lbldiscount.TabIndex = 53
        Me.lbldiscount.Text = "99,999,999.00"
        '
        'cargobox
        '
        Me.cargobox.FormattingEnabled = True
        Me.cargobox.Location = New System.Drawing.Point(421, 244)
        Me.cargobox.Name = "cargobox"
        Me.cargobox.Size = New System.Drawing.Size(120, 95)
        Me.cargobox.TabIndex = 60
        '
        'Label7
        '
        Me.Label7.AutoSize = True
        Me.Label7.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label7.Location = New System.Drawing.Point(306, 386)
        Me.Label7.Name = "Label7"
        Me.Label7.Size = New System.Drawing.Size(119, 13)
        Me.Label7.TabIndex = 52
        Me.Label7.Text = "TOTAL DISCOUNT:"
        '
        'servicebox
        '
        Me.servicebox.FormattingEnabled = True
        Me.servicebox.Location = New System.Drawing.Point(294, 244)
        Me.servicebox.Name = "servicebox"
        Me.servicebox.Size = New System.Drawing.Size(120, 95)
        Me.servicebox.TabIndex = 59
        '
        'unitbox
        '
        Me.unitbox.FormattingEnabled = True
        Me.unitbox.Location = New System.Drawing.Point(168, 244)
        Me.unitbox.Name = "unitbox"
        Me.unitbox.Size = New System.Drawing.Size(120, 95)
        Me.unitbox.TabIndex = 58
        '
        'lblduescipsi
        '
        Me.lblduescipsi.AutoSize = True
        Me.lblduescipsi.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblduescipsi.Location = New System.Drawing.Point(605, 410)
        Me.lblduescipsi.Name = "lblduescipsi"
        Me.lblduescipsi.Size = New System.Drawing.Size(96, 13)
        Me.lblduescipsi.TabIndex = 47
        Me.lblduescipsi.Text = "100,000,000.00"
        '
        'qtybox
        '
        Me.qtybox.FormattingEnabled = True
        Me.qtybox.Location = New System.Drawing.Point(42, 244)
        Me.qtybox.Name = "qtybox"
        Me.qtybox.Size = New System.Drawing.Size(120, 95)
        Me.qtybox.TabIndex = 57
        '
        'lblppa
        '
        Me.lblppa.AutoSize = True
        Me.lblppa.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblppa.Location = New System.Drawing.Point(167, 422)
        Me.lblppa.Name = "lblppa"
        Me.lblppa.Size = New System.Drawing.Size(89, 13)
        Me.lblppa.TabIndex = 46
        Me.lblppa.Text = "99,999,999.00"
        '
        'lblvat
        '
        Me.lblvat.AutoSize = True
        Me.lblvat.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblvat.Location = New System.Drawing.Point(446, 398)
        Me.lblvat.Name = "lblvat"
        Me.lblvat.Size = New System.Drawing.Size(89, 13)
        Me.lblvat.TabIndex = 45
        Me.lblvat.Text = "99,999,999.00"
        '
        'lblscipsi
        '
        Me.lblscipsi.AutoSize = True
        Me.lblscipsi.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblscipsi.Location = New System.Drawing.Point(306, 422)
        Me.lblscipsi.Name = "lblscipsi"
        Me.lblscipsi.Size = New System.Drawing.Size(89, 13)
        Me.lblscipsi.TabIndex = 44
        Me.lblscipsi.Text = "99,999,999.00"
        '
        'lbltotal
        '
        Me.lbltotal.AutoSize = True
        Me.lbltotal.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbltotal.Location = New System.Drawing.Point(167, 398)
        Me.lbltotal.Name = "lbltotal"
        Me.lbltotal.Size = New System.Drawing.Size(89, 13)
        Me.lbltotal.TabIndex = 43
        Me.lbltotal.Text = "99,999,999.00"
        '
        'Label15
        '
        Me.Label15.AutoSize = True
        Me.Label15.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label15.Location = New System.Drawing.Point(605, 386)
        Me.Label15.Name = "Label15"
        Me.Label15.Size = New System.Drawing.Size(104, 26)
        Me.Label15.TabIndex = 13
        Me.Label15.Text = "TOTAL AMOUNT" & Global.Microsoft.VisualBasic.ChrW(13) & Global.Microsoft.VisualBasic.ChrW(10) & "DUE TO SCIPSI:" & Global.Microsoft.VisualBasic.ChrW(13) & Global.Microsoft.VisualBasic.ChrW(10)
        '
        'Label14
        '
        Me.Label14.AutoSize = True
        Me.Label14.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label14.Location = New System.Drawing.Point(446, 386)
        Me.Label14.Name = "Label14"
        Me.Label14.Size = New System.Drawing.Size(62, 13)
        Me.Label14.TabIndex = 12
        Me.Label14.Text = "12% VAT:"
        '
        'Label13
        '
        Me.Label13.AutoSize = True
        Me.Label13.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label13.Location = New System.Drawing.Point(306, 410)
        Me.Label13.Name = "Label13"
        Me.Label13.Size = New System.Drawing.Size(97, 13)
        Me.Label13.TabIndex = 11
        Me.Label13.Text = "SCIPSI SHARE:" & Global.Microsoft.VisualBasic.ChrW(13) & Global.Microsoft.VisualBasic.ChrW(10)
        '
        'Label12
        '
        Me.Label12.AutoSize = True
        Me.Label12.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label12.Location = New System.Drawing.Point(167, 410)
        Me.Label12.Name = "Label12"
        Me.Label12.Size = New System.Drawing.Size(81, 13)
        Me.Label12.TabIndex = 10
        Me.Label12.Text = "PPA SHARE:"
        '
        'lblBill
        '
        Me.lblBill.AutoSize = True
        Me.lblBill.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblBill.Location = New System.Drawing.Point(672, 96)
        Me.lblBill.Name = "lblBill"
        Me.lblBill.Size = New System.Drawing.Size(90, 13)
        Me.lblBill.TabIndex = 8
        Me.lblBill.Text = "BILL NUMBER"
        '
        'lblcleintname
        '
        Me.lblcleintname.AutoSize = True
        Me.lblcleintname.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblcleintname.Location = New System.Drawing.Point(96, 115)
        Me.lblcleintname.Name = "lblcleintname"
        Me.lblcleintname.Size = New System.Drawing.Size(90, 13)
        Me.lblcleintname.TabIndex = 7
        Me.lblcleintname.Text = "CLIENT NAME"
        '
        'lbltime
        '
        Me.lbltime.AutoSize = True
        Me.lbltime.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbltime.Location = New System.Drawing.Point(672, 154)
        Me.lbltime.Name = "lbltime"
        Me.lbltime.Size = New System.Drawing.Size(40, 13)
        Me.lbltime.TabIndex = 6
        Me.lbltime.Text = "DATE"
        '
        'lblBillingname
        '
        Me.lblBillingname.AutoSize = True
        Me.lblBillingname.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblBillingname.Location = New System.Drawing.Point(585, 430)
        Me.lblBillingname.Name = "lblBillingname"
        Me.lblBillingname.Size = New System.Drawing.Size(45, 13)
        Me.lblBillingname.TabIndex = 2
        Me.lblBillingname.Text = "Label4"
        '
        'Label5
        '
        Me.Label5.AutoSize = True
        Me.Label5.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label5.Location = New System.Drawing.Point(167, 386)
        Me.Label5.Name = "Label5"
        Me.Label5.Size = New System.Drawing.Size(114, 13)
        Me.Label5.TabIndex = 1
        Me.Label5.Text = "TOTAL CHARGES:"
        '
        'DataGridPrint
        '
        Me.DataGridPrint.AllowUserToAddRows = False
        Me.DataGridPrint.AllowUserToDeleteRows = False
        DataGridViewCellStyle1.BackColor = System.Drawing.Color.White
        Me.DataGridPrint.AlternatingRowsDefaultCellStyle = DataGridViewCellStyle1
        Me.DataGridPrint.BackgroundColor = System.Drawing.Color.White
        Me.DataGridPrint.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.DataGridPrint.ColumnHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.[Single]
        DataGridViewCellStyle2.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle2.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle2.ForeColor = System.Drawing.SystemColors.ControlText
        DataGridViewCellStyle2.SelectionBackColor = System.Drawing.Color.LightSteelBlue
        DataGridViewCellStyle2.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle2.WrapMode = System.Windows.Forms.DataGridViewTriState.[True]
        Me.DataGridPrint.ColumnHeadersDefaultCellStyle = DataGridViewCellStyle2
        Me.DataGridPrint.ColumnHeadersHeight = 24
        Me.DataGridPrint.ColumnHeadersVisible = False
        Me.DataGridPrint.Columns.AddRange(New System.Windows.Forms.DataGridViewColumn() {Me.Column1, Me.Column2, Me.Column3, Me.Column4, Me.Column5, Me.Column6})
        Me.DataGridPrint.Cursor = System.Windows.Forms.Cursors.Hand
        Me.DataGridPrint.EnableHeadersVisualStyles = False
        Me.DataGridPrint.GridColor = System.Drawing.Color.White
        Me.DataGridPrint.Location = New System.Drawing.Point(50, 221)
        Me.DataGridPrint.MultiSelect = False
        Me.DataGridPrint.Name = "DataGridPrint"
        Me.DataGridPrint.ReadOnly = True
        Me.DataGridPrint.RowHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.[Single]
        DataGridViewCellStyle3.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle3.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle3.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle3.ForeColor = System.Drawing.Color.White
        DataGridViewCellStyle3.SelectionBackColor = System.Drawing.Color.White
        DataGridViewCellStyle3.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle3.WrapMode = System.Windows.Forms.DataGridViewTriState.[True]
        Me.DataGridPrint.RowHeadersDefaultCellStyle = DataGridViewCellStyle3
        Me.DataGridPrint.RowHeadersVisible = False
        Me.DataGridPrint.RowHeadersWidth = 25
        Me.DataGridPrint.RowHeadersWidthSizeMode = System.Windows.Forms.DataGridViewRowHeadersWidthSizeMode.DisableResizing
        DataGridViewCellStyle4.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle4.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle4.ForeColor = System.Drawing.Color.Black
        DataGridViewCellStyle4.SelectionBackColor = System.Drawing.Color.White
        DataGridViewCellStyle4.SelectionForeColor = System.Drawing.Color.Black
        Me.DataGridPrint.RowsDefaultCellStyle = DataGridViewCellStyle4
        Me.DataGridPrint.RowTemplate.Height = 18
        Me.DataGridPrint.RowTemplate.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.DataGridPrint.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.CellSelect
        Me.DataGridPrint.Size = New System.Drawing.Size(751, 162)
        Me.DataGridPrint.TabIndex = 40
        '
        'Column1
        '
        Me.Column1.DataPropertyName = "it_qty"
        Me.Column1.HeaderText = "QTY"
        Me.Column1.Name = "Column1"
        Me.Column1.ReadOnly = True
        Me.Column1.Width = 73
        '
        'Column2
        '
        Me.Column2.DataPropertyName = "it_unit"
        Me.Column2.HeaderText = "UNIT"
        Me.Column2.Name = "Column2"
        Me.Column2.ReadOnly = True
        Me.Column2.Width = 60
        '
        'Column3
        '
        Me.Column3.DataPropertyName = "it_service"
        Me.Column3.HeaderText = "DESCRIPTION"
        Me.Column3.Name = "Column3"
        Me.Column3.ReadOnly = True
        Me.Column3.Width = 197
        '
        'Column4
        '
        Me.Column4.DataPropertyName = "it_cargo"
        Me.Column4.HeaderText = "ITEM CODE"
        Me.Column4.Name = "Column4"
        Me.Column4.ReadOnly = True
        Me.Column4.Width = 118
        '
        'Column5
        '
        Me.Column5.DataPropertyName = "it_rate"
        Me.Column5.HeaderText = "RATE"
        Me.Column5.Name = "Column5"
        Me.Column5.ReadOnly = True
        Me.Column5.Width = 105
        '
        'Column6
        '
        Me.Column6.DataPropertyName = "it_gross"
        Me.Column6.HeaderText = "AMOUNT"
        Me.Column6.Name = "Column6"
        Me.Column6.ReadOnly = True
        '
        'PrintDocument1
        '
        '
        'cargodesc
        '
        Me.cargodesc.FormattingEnabled = True
        Me.cargodesc.Location = New System.Drawing.Point(449, 154)
        Me.cargodesc.Name = "cargodesc"
        Me.cargodesc.Size = New System.Drawing.Size(120, 95)
        Me.cargodesc.TabIndex = 58
        '
        'frmPrintService
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(6.0!, 13.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(819, 626)
        Me.Controls.Add(Me.Panel1)
        Me.Name = "frmPrintService"
        Me.Text = "frmPrintService"
        Me.Panel1.ResumeLayout(False)
        Me.Panel1.PerformLayout()
        CType(Me.DataGridPrint, System.ComponentModel.ISupportInitialize).EndInit()
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents Panel1 As System.Windows.Forms.Panel
    Friend WithEvents lblduescipsi As System.Windows.Forms.Label
    Friend WithEvents lblppa As System.Windows.Forms.Label
    Friend WithEvents lblvat As System.Windows.Forms.Label
    Friend WithEvents lblscipsi As System.Windows.Forms.Label
    Friend WithEvents lbltotal As System.Windows.Forms.Label
    Friend WithEvents Label15 As System.Windows.Forms.Label
    Friend WithEvents Label14 As System.Windows.Forms.Label
    Friend WithEvents Label13 As System.Windows.Forms.Label
    Friend WithEvents Label12 As System.Windows.Forms.Label
    Friend WithEvents lblBill As System.Windows.Forms.Label
    Friend WithEvents lblcleintname As System.Windows.Forms.Label
    Friend WithEvents lbltime As System.Windows.Forms.Label
    Friend WithEvents lblBillingname As System.Windows.Forms.Label
    Friend WithEvents Label5 As System.Windows.Forms.Label
    Friend WithEvents DataGridPrint As System.Windows.Forms.DataGridView
    Friend WithEvents lbldiscount As System.Windows.Forms.Label
    Friend WithEvents Label7 As System.Windows.Forms.Label
    Friend WithEvents PrintDocument1 As System.Drawing.Printing.PrintDocument
    Friend WithEvents Column1 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column2 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column3 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column4 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column5 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents Column6 As System.Windows.Forms.DataGridViewTextBoxColumn
    Friend WithEvents lblParticulars As System.Windows.Forms.Label
    Friend WithEvents grossbox As System.Windows.Forms.ListBox
    Friend WithEvents ratebox As System.Windows.Forms.ListBox
    Friend WithEvents cargobox As System.Windows.Forms.ListBox
    Friend WithEvents servicebox As System.Windows.Forms.ListBox
    Friend WithEvents unitbox As System.Windows.Forms.ListBox
    Friend WithEvents qtybox As System.Windows.Forms.ListBox
    Friend WithEvents cargodesc As System.Windows.Forms.ListBox
End Class
